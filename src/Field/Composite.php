<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Field\ValidationResult as FieldValidationResult;
use Meraki\Schema\AggregatedValidationResult;
use Meraki\Schema\ResolvedField;
use Meraki\Schema\ValidationStatus;
use Meraki\Schema\Property;
use IteratorAggregate;
use Countable;
use InvalidArgumentException;

/**
 * @template AcceptedType of mixed
 * @extends Field<AcceptedType>
 */
abstract class Composite extends Field implements IteratorAggregate, Countable
{
	public Field\Set $fields;

	public function __construct(public readonly Property\Name $name, Field ...$fields)
	{
		$this->fields = (new Field\Set(...$fields))->prefixNamesWith($name);
	}

	public function rename(Property\Name $name): static
	{
		// Re-prefix each sub-field under the new (possibly nested) name, replacing the
		// sub-field's current prefix rather than stacking onto it. This lets a composite
		// be nested inside another composite/collection (e.g. a per-lesson address)
		// without doubling its own segment (`lessons.pickup.pickup.street`). For a
		// sub-field that is itself a composite, rename() recurses.
		$renamed = new Field\Set(...array_map(
			static fn(Field $field): Field => $field->rename($field->name->removePrefix()->prefixWith($name)),
			$this->fields->__toArray(),
		));

		return clone($this, ['name' => $name, 'fields' => $renamed]);
	}

	/** @param AcceptedType $value */
	public function prefill($value): static
	{
		parent::prefill($value);
		$value = $this->defaultValue->unwrap();

		// Unusable input is kept as it came (see process()), so there is nothing to hand
		// the sub-fields. validate() reports it against the composite.
		if (!is_array($value)) {
			return $this;
		}

		foreach ($this->fields as $field) {
			$field->prefill($value[(string)$field->name]);
		}

		return $this;
	}

	protected function valueProvided(Property\Value $value): bool
	{
		// Input that could not be mapped onto the sub-fields is still input. Treating it
		// as absent would fall back to the default and quietly validate something the
		// caller never sent; it has to reach validate() to be reported.
		if (!is_array($value->unwrap())) {
			return $value->unwrap() !== null;
		}

		// For composite fields, we consider the value provided if at least one subfield has a value other than null.
		foreach ($this->fields as $field) {
			if (isset($value->unwrap()[(string)$field->name]) && $value->unwrap()[(string)$field->name] !== null) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolves each sub-field against its slice of the submitted value, without checking
	 * anything and without writing to any of them.
	 *
	 * @param list<\Meraki\Schema\Rule\AppliedOutcome> $appliedOutcomes
	 */
	public function resolve(mixed $given, array $appliedOutcomes = []): CompositeValidationResult
	{
		$value = $this->resolvedValueFor($given)->unwrap();
		$resolved = [];

		foreach ($this->fields as $field) {
			// Unusable input has nothing to hand the sub-fields; validate() reports it
			// against the composite.
			$slice = is_array($value) ? ($value[(string) $field->name] ?? null) : null;

			foreach ($this->flatten($field->resolve($slice)) as $leaf) {
				$resolved[] = $leaf;
			}
		}

		return new CompositeValidationResult($this, ...$resolved);
	}

	/**
	 * @param list<\Meraki\Schema\Rule\AppliedOutcome> $appliedOutcomes
	 */
	public function validate(mixed $given, array $appliedOutcomes = []): CompositeValidationResult
	{
		$value = $this->resolvedValueFor($given);
		$raw = $value->unwrap();

		// Unusable input is a shape failure on the composite, reported before anything
		// else: being optional excuses an absent value, never a malformed one.
		if (!$this->validateValue($raw)) {
			return $this->failShapeOf($given, $appliedOutcomes);
		}

		// An optional composite that was left empty is skipped, not failed.
		if ($this->optional && !$this->valueProvided($value)) {
			return $this->skipEveryFieldOf($given, $appliedOutcomes);
		}

		/** @var array<string, ResolvedField> $byName */
		$byName = [];
		/** @var array<string, true> $unusable */
		$unusable = [];

		// The shape of each sub-field first, so a constraint never speaks to a value that
		// was never there.
		foreach ($this->fields as $field) {
			$name = (string) $field->name;
			$slice = $raw[$name] ?? null;
			$resolved = $field->resolve($slice);

			if (!$resolved instanceof ResolvedField) {
				throw new InvalidArgumentException(sprintf(
					'Sub-field "%s" of "%s" is itself composite, which is not supported.',
					$name,
					(string) $this->name,
				));
			}

			// An optional sub-field left empty is not an error: skip it outright rather
			// than type-checking a null that every field type rejects.
			if ($field->optional && !$field->valueProvided(new Property\Value($resolved->value))) {
				$byName[$name] = $resolved->withResults(ConstraintValidationResult::skip('type'));
				$unusable[$name] = true;
				continue;
			}

			$shape = $field->validateValue($resolved->value);
			$status = match ($shape) {
				true => ValidationStatus::Passed,
				false => ValidationStatus::Failed,
				default => ValidationStatus::Skipped,
			};

			$byName[$name] = $resolved->withResults(new ConstraintValidationResult($status, 'type'));

			if ($shape !== true) {
				$unusable[$name] = true;
			}
		}

		// Constraints the composite applies across its parts, named for the part they
		// speak about.
		foreach ($this->constraints() as $constraint) {
			$constraintName = $constraint->name;
			$name = $this->resolveConstraintNameToFieldName($constraintName);

			if (!isset($byName[$name])) {
				throw new InvalidArgumentException("Constraint '$constraintName' does not correspond to any field in the composite.");
			}

			if (isset($unusable[$name])) {
				$byName[$name] = $byName[$name]->add($constraint->skipped());
				continue;
			}

			$result = $constraint->against($raw);
			$byName[$name] = $byName[$name]->add($result);
			$outcome = $result->passed() ? true : ($result->failed() ? false : null);

			if ($outcome === false) {
				$unusable[$name] = true;
			}
		}

		// Each sub-field's own constraints.
		foreach ($this->fields as $field) {
			$name = (string) $field->name;

			foreach ($field->constraints() as $constraint) {
				$byName[$name] = $byName[$name]->add(isset($unusable[$name])
					? $constraint->skipped()
					: $constraint->against($byName[$name]->value));
			}
		}

		return new CompositeValidationResult($this, ...array_values($byName));
	}

	/**
	 * The value could not be mapped onto the sub-fields at all, so the failure belongs to
	 * the composite and the sub-fields are skipped: nothing reached them.
	 *
	 * @param list<\Meraki\Schema\Rule\AppliedOutcome> $appliedOutcomes
	 */
	private function failShapeOf(mixed $given, array $appliedOutcomes): CompositeValidationResult
	{
		$results = [(new ResolvedField($this, $given, $given, $appliedOutcomes))
			->withResults(ConstraintValidationResult::fail('type'))];

		foreach ($this->fields as $field) {
			$results[] = (new ResolvedField($field, null, null))
				->withResults(ConstraintValidationResult::skip('type'));
		}

		return new CompositeValidationResult($this, ...$results);
	}

	/**
	 * @param list<\Meraki\Schema\Rule\AppliedOutcome> $appliedOutcomes
	 */
	private function skipEveryFieldOf(mixed $given, array $appliedOutcomes): CompositeValidationResult
	{
		$value = $this->resolvedValueFor($given)->unwrap();
		$results = [];

		foreach ($this->fields as $field) {
			$slice = is_array($value) ? ($value[(string) $field->name] ?? null) : null;
			$results[] = (new ResolvedField($field, $slice, $slice))
				->withResults(ConstraintValidationResult::skip('type'));
		}

		return new CompositeValidationResult($this, ...$results);
	}

	/**
	 * @return list<ResolvedField>
	 */
	private function flatten(AggregatedValidationResult $result): array
	{
		if ($result instanceof ResolvedField) {
			return [$result];
		}

		$flat = [];

		foreach ($result as $child) {
			foreach ($this->flatten($child) as $leaf) {
				$flat[] = $leaf;
			}
		}

		return $flat;
	}

	/**
	 * Resolves the constraint name to the corresponding field name.
	 *
	 * Constraint names are '{fully-qualified sub-field name}.{constraint}', so the field
	 * is everything up to the last segment. Taken from the right rather than the left so
	 * that it keeps working when the composite is itself nested — a per-item address in a
	 * collection names its constraints 'lessons.pickup.line1.type'.
	 */
	private function resolveConstraintNameToFieldName(string $constraintName): string
	{
		$separator = strrpos($constraintName, Property\Name::PREFIX_SEPARATOR);

		if ($separator === false) {
			throw new InvalidArgumentException("Invalid constraint name: '$constraintName'. Expected format is 'subFieldName.constraintName'.");
		}

		return substr($constraintName, 0, $separator);
	}

	private function skipValidationOfAllFields(): CompositeValidationResult
	{
		$fieldResults = [];

		foreach ($this->fields as $field) {
			$fieldResults[] = new ValidationResult($field, new ConstraintValidationResult(ValidationStatus::Skipped, 'type'));
		}

		return new CompositeValidationResult($this, ...$fieldResults);
	}

	/**
	 * The value could not be mapped onto the sub-fields at all, so the failure belongs to
	 * the composite. The sub-fields are skipped rather than failed: nothing reached them,
	 * and reporting a fault against each one would bury the single real problem.
	 */
	private function failShapeOfAllFields(): CompositeValidationResult
	{
		$fieldResults = [new ValidationResult($this, ConstraintValidationResult::fail('type'))];

		foreach ($this->fields as $field) {
			$fieldResults[] = new ValidationResult($field, ConstraintValidationResult::skip('type'));
		}

		return new CompositeValidationResult($this, ...$fieldResults);
	}

	public function getIterator(): \Traversable
	{
		return $this->fields->getIterator();
	}

	public function count(): int
	{
		return $this->fields->count();
	}

	public function __isset(string $name): bool
	{
		$name = self::camelCaseToSnakeCase($name);

		return $this->fields->findByName($this->name->__toString() . $this->name::PREFIX_SEPARATOR . $name) !== null;
	}

	public function __get($name): Field
	{
		$name = self::camelCaseToSnakeCase($name);
		$field = $this->fields->findByName($this->name->__toString() . $this->name::PREFIX_SEPARATOR . $name);

		if ($field) {
			return $field;
		}

		throw new InvalidArgumentException("Field '$name' does not exist.");
	}

	public function validateValue(mixed $value): bool
	{
		// process() maps a usable value onto the sub-fields and leaves anything else
		// untouched, so a non-array here is input that was never a set of sub-field values.
		return is_array($value);
	}

	private static function camelCaseToSnakeCase(string $input): string
	{
		return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($input)));
	}

	/**
	 * @param AcceptedType|null $value
	 */
	protected function process($value): Property\Value
	{
		$value = parent::process($value)->unwrap();

		if ($value === null) {
			$value = [];
		} elseif (is_object($value)) {
			$value = get_object_vars($value);
		}

		// Input that is not a set of sub-field values cannot be mapped onto them. Keep it
		// as it came rather than raising: form input is attacker-controlled, so this has
		// to surface as a validation failure and not as a fatal. validateValue() rejects
		// anything that is not an array, and validate() reports it against the composite.
		if (!is_array($value)) {
			return new Property\Value($value);
		}

		// Map the incoming value onto the subfields, keyed by each subfield's
		// local name. Callers nest naturally, e.g.
		//   ['price' => ['amount' => '1500', 'currency' => 'AUD']]
		// Fully-qualified keys ('price.amount', ...) are not accepted.
		$normalized = [];

		foreach ($this->fields as $field) {
			$fullName = (string) $field->name;
			$localName = (string) $field->name->removePrefix();

			$normalized[$fullName] = $value[$localName] ?? null;
		}

		return new Property\Value($normalized);
	}
}
