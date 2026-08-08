<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Field\ValidationResult as FieldValidationResult;
use Meraki\Schema\ValidationStatus;
use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Property;
use IteratorAggregate;
use Countable;
use InvalidArgumentException;

/**
 * @phpstan-import-type SerializedField from Field
 * @template AcceptedType of mixed
 * @template TSerialized of SerializedField
 * @extends Field<AcceptedType, TSerialized>
 */
abstract class Composite extends Field implements IteratorAggregate, Countable
{
	public Field\Set $fields;

	public function __construct(Property\Name $name, Field ...$fields)
	{
		$this->fields = new Field\Set(...$fields);
		$this->fields->prefixNamesWith($name);

		parent::__construct($name);
	}

	public function rename(Property\Name $name): static
	{
		// Re-prefix each sub-field under the new (possibly nested) name, replacing the
		// sub-field's current prefix rather than stacking onto it. This lets a composite
		// be nested inside another composite/collection (e.g. a per-lesson address)
		// without doubling its own segment (`lessons.pickup.pickup.street`). For a
		// sub-field that is itself a composite, rename() recurses.
		foreach ($this->fields as $field) {
			$field->rename($field->name->removePrefix()->prefixWith($name));
		}

		$this->name = $name;

		return $this;
	}

	/** @param AcceptedType $value */
	public function prefill($value): static
	{
		parent::prefill($value);
		$value = $this->defaultValue->unwrap();

		foreach ($this->fields as $field) {
			$field->prefill($value[(string)$field->name]);
		}

		return $this;
	}

	/** @param AcceptedType|null $value */
	public function input($value): static
	{
		parent::input($value);
		$value = $this->resolvedValue->unwrap();

		foreach ($this->fields as $field) {
			$field->input($value[(string)$field->name]);
		}

		return $this;
	}

	protected function valueProvided(Property\Value $value): bool
	{
		if (!is_array($value->unwrap())) {
			return false;
		}

		// For composite fields, we consider the value provided if at least one subfield has a value other than null.
		foreach ($this->fields as $field) {
			if (isset($value->unwrap()[(string)$field->name]) && $value->unwrap()[(string)$field->name] !== null) {
				return true;
			}
		}

		return false;
	}

	public function validate(): CompositeValidationResult
	{
		/** @var array<string, FieldValidationResult> $fieldResults */
		$fieldResults = [];
		/** @var array<string, Field> $fieldsToSkip */
		$fieldsToSkip = [];

		$value = $this->resolvedValue;

		// skip validation of all fields if the type validation fails
		// or if the value is not provided and field is optional
		if (($this->optional && !$this->valueProvided($value)) || !$this->validateValue($value->unwrap())) {
			return $this->skipValidationOfAllFields();
		}

		// First validate types of each subfield
		foreach ($this->fields as $field) {
			$fieldName = (string)$field->name;

			// An optional sub-field that was left empty is not an error. Skip it outright
			// rather than type-checking the null (which every field type rejects), and mark
			// it so its constraints below are skipped too.
			if ($field->optional && !$field->hasValue()) {
				$fieldResults[$fieldName] = new FieldValidationResult($field, new ConstraintValidationResult(ValidationStatus::Skipped, 'type'));
				$fieldsToSkip[$fieldName] = $field;
				continue;
			}

			$result = $field->validateValue($field->resolvedValue->unwrap());

			if ($result === true) {
				$fieldResults[$fieldName] = new FieldValidationResult($field, new ConstraintValidationResult(ValidationStatus::Passed, 'type'));
				continue;
			}

			$status = $result === null ? ValidationStatus::Skipped : ValidationStatus::Failed;
			$fieldResults[$fieldName] = new FieldValidationResult($field, new ConstraintValidationResult($status, 'type'));
			$fieldsToSkip[$fieldName] = $field;
		}

		// composite constraints
		foreach ($this->getConstraints() as $constraintName => $constraintValidator) {
			$fieldName = $this->resolveConstraintNameToFieldName($constraintName);

			if (!isset($fieldResults[$fieldName])) {
				throw new InvalidArgumentException("Constraint '$constraintName' does not correspond to any field in the composite.");
			}

			$fieldValidationResult = $fieldResults[$fieldName];
			$field = $fieldValidationResult->field;

			// Skip constraint if the field failed/skipped type validation. An optional
			// sub-field left empty is already in $fieldsToSkip; one that *was* filled in
			// must still have its constraints run.
			if (isset($fieldsToSkip[$fieldName])) {
				$fieldResults[$fieldName] = $fieldValidationResult->add(new ConstraintValidationResult(ValidationStatus::Skipped, $constraintName));
				continue;
			}

			// run validator
			$result = $constraintValidator($value->unwrap());

			if ($result === false) {
				$fieldValidationResult = $fieldValidationResult->add(new ConstraintValidationResult(ValidationStatus::Failed, $constraintName));
				$fieldsToSkip[$fieldName] = $field;		// Mark field to skip further validation of constraints
			} elseif ($result === true) {
				$fieldValidationResult = $fieldValidationResult->add(new ConstraintValidationResult(ValidationStatus::Passed, $constraintName));
			} else {
				$fieldValidationResult = $fieldValidationResult->add(new ConstraintValidationResult(ValidationStatus::Skipped, $constraintName));
			}

			$fieldResults[$fieldName] = $fieldValidationResult;
		}

		// sub-field constraints
		foreach ($this->fields as $field) {
			$fieldName = (string)$field->name;

			// Validate each field's constraints
			foreach ($field->getConstraints() as $constraintName => $constraintValidator) {
				if (isset($fieldsToSkip[$fieldName])) {
					$fieldResults[$fieldName] = $fieldResults[$fieldName]->add(new ConstraintValidationResult(ValidationStatus::Skipped, $constraintName));
					continue;
				}

				$fieldValidationResult = $fieldResults[$fieldName];
				$result = $constraintValidator($field->resolvedValue->unwrap());

				if ($result === false) {
					$fieldValidationResult = $fieldValidationResult->add(new ConstraintValidationResult(ValidationStatus::Failed, $constraintName));
				} elseif ($result === true) {
					$fieldValidationResult = $fieldValidationResult->add(new ConstraintValidationResult(ValidationStatus::Passed, $constraintName));
				} else {
					$fieldValidationResult = $fieldValidationResult->add(new ConstraintValidationResult(ValidationStatus::Skipped, $constraintName));
				}

				$fieldResults[$fieldName] = $fieldValidationResult;
			}
		}

		return new CompositeValidationResult($this, ...array_values($fieldResults));
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
		return true;
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

		if (!is_array($value)) {
			throw new InvalidArgumentException('Input value must be an array, an object, or null.');
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
