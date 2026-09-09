<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Property;
use Meraki\Schema\AggregatedValidationResult;
use Meraki\Schema\Field\ValidationResult;
use Meraki\Schema\Field\CompositeValidationResult;
use Meraki\Schema\Field\Constraint;
use Meraki\Schema\Field\ConstraintValidationResult;
use Meraki\Schema\Rule\FieldBuilder;
use Closure;
use InvalidArgumentException;
use LogicException;

/**
 * @template AcceptedType of mixed
 */
abstract class Field
{
	/**
	 * The name of the field.
	 *
	 * This is used to identify the field in the schema and
	 * should be unique within a schema.
	 *
	 * External code should not modify this property
	 */
	abstract public Property\Name $name { get; }


	/**
	 * The default value of the field.
	 *
	 * This property is used when no input was given.
	 *
	 * External code should not modify this property
	 */
	/**
	 * The author's default, computed on first read rather than in a constructor: what an
	 * unset default *is* depends on the field's own process(), so it cannot be a static
	 * initialiser, and requiring a constructor call for it is what this removes.
	 */
	public Property\Value $defaultValue {
		get => $this->authoredDefault ?? $this->process(null);
	}

	private ?Property\Value $authoredDefault = null;



	/**
	 * Indicates whether this field requires input.
	 *
	 * External code should not modify this property
	 */
	public bool $optional = false;


	/**
	 * The schema this field belongs to, set when added via {@see Facade::addField()}.
	 * Enables {@see self::pairWith()} to add a paired field and register rules.
	 */
	public ?Facade $schema = null;

	/**
	 * Properties that exist for internal wiring rather than as part of the field's
	 * addressable API. {@see self::$schema} points back at the field's owner, so a
	 * scope stepping into it would climb to the root and walk the path forever.
	 *
	 * Everything else public on a field stays addressable: a field's public properties
	 * are its API, and '#/fields/x/min' or '#/fields/x/optional' are valid targets.
	 */
	public const NOT_ADDRESSABLE = ['schema'];

	/**
	 * Renames the field to a new name.
	 *
	 * @param Property\Name $name The new name for the field.
	 */
	public function rename(Property\Name $name): static
	{
		/** @phpstan-ignore assign.propertyReadOnly (clone() may set a readonly property the concrete field declares; the base sees only its getter) */
		return clone($this, ['name' => $name]);
	}

	/**
	 * Marks the field as optional, meaning it can be left empty
	 * without causing a validation error.
	 */
	public function makeOptional(): static
	{
		$this->optional = true;

		return $this;
	}

	public function require(): static
	{
		$this->optional = false;

		return $this;
	}



	/**
	 * Declare a relationship with another field. The paired field is added to this
	 * field's schema (a duplicate name throws). The configurator runs immediately,
	 * bound so `$this` is this field, and uses the supplied {@see FieldBuilder} to
	 * capture declarative rules (which therefore serialize like any other rule).
	 *
	 * @param Closure(FieldBuilder, Field, Facade): void $configurator
	 */
	public function pairWith(Field $paired, Closure $configurator): static
	{
		if ($this->schema === null) {
			throw new LogicException('pairWith() requires the owner field to be added to a schema first.');
		}

		if ($this->schema->fields->findByName($paired->name) !== null) {
			throw new InvalidArgumentException("A field named '{$paired->name}' already exists.");
		}

		$this->schema->addField($paired);

		$builder = new FieldBuilder();
		$configurator->call($this, $builder, $paired, $this->schema);

		foreach ($builder->rules() as $rule) {
			$this->schema->addRule($rule);
		}

		return $this;
	}


	/**
	 * Sets the default value for the field, which will be used when
	 * no input has been given.
	 *
	 * @param AcceptedType|null $value
	 */
	public function prefill($value): static
	{
		$this->authoredDefault = $this->process($value);

		return $this;
	}

	public function equals(mixed $other): bool
	{
		return $other instanceof static && $this->name->equals($other->name);
	}



	/**
	 * Checks if the value given is considered as "input provided".
	 *
	 * Defaults to checking if the value is not null.
	 */
	protected function valueProvided(Property\Value $value): bool
	{
		return $value->unwrap() !== null;
	}

	/**
	 * Resolves a submitted value against this field, without checking it.
	 *
	 * This is the seam: the one place a value meets a field. Nothing is written back, so
	 * the field is unchanged and safe to share — resolving the same field concurrently
	 * with different values cannot interfere.
	 *
	 * The result is {@see ValidationStatus::Pending}: a form is rendered before it is
	 * submitted, and that state needs a name.
	 *
	 * @param AcceptedType|null $given exactly what was submitted, or null if nothing was
	 * @param list<Rule\AppliedOutcome> $appliedOutcomes rules that altered this field
	 */
	public function resolve(mixed $given, array $appliedOutcomes = []): AggregatedValidationResult
	{
		return new ResolvedField($this, $given, $this->resolvedValueFor($given)->unwrap(), $appliedOutcomes);
	}

	/**
	 * What this field would actually validate, given what was submitted: the submitted
	 * value, or the author's default when nothing usable was.
	 *
	 * @param AcceptedType|null $given
	 */
	final public function resolvedValueFor(mixed $given): Property\Value
	{
		$submitted = $this->process($given);

		return $this->valueProvided($submitted) ? $submitted : $this->defaultValue;
	}

	/**
	 * Resolves a submitted value and checks it against this field's constraints.
	 *
	 * @param AcceptedType|null $given
	 * @param list<Rule\AppliedOutcome> $appliedOutcomes
	 */
	public function validate(mixed $given, array $appliedOutcomes = []): AggregatedValidationResult
	{
		// Built here rather than through resolve(), which subclasses widen to return a
		// result per sub-field; this also resolves the value once instead of twice.
		$value = $this->resolvedValueFor($given);

		return (new ResolvedField($this, $given, $value->unwrap(), $appliedOutcomes))
			->withResults(...$this->check($value));
	}

	/**
	 * Evaluates this field's shape and constraints against an already-resolved value.
	 *
	 * Shape first: if there is no usable value, the constraints have nothing to speak to
	 * and are skipped rather than failed, so an error report names the real problem once
	 * instead of once per constraint.
	 *
	 * @return list<ConstraintValidationResult>
	 */
	protected function check(Property\Value $value): array
	{
		$notProvided = !$this->valueProvided($value);

		if ($notProvided) {
			// Absent input is only acceptable when the field says so.
			$shape = $this->optional
				? ConstraintValidationResult::skip('type')
				: ConstraintValidationResult::fail('type');

			return [$shape, ...$this->skipEveryConstraint()];
		}

		if (!$this->validateValue($value->unwrap())) {
			return [ConstraintValidationResult::fail('type'), ...$this->skipEveryConstraint()];
		}

		return [
			ConstraintValidationResult::pass('type'),
			...$this->constraints()->against($value->unwrap()),
		];
	}

	/**
	 * @return list<ConstraintValidationResult>
	 */
	private function skipEveryConstraint(): array
	{
		return $this->constraints()->allSkipped();
	}

	/**
	 * The checks this field makes, each carrying the name it reports under, the part of a
	 * structured value it concerns, and the bound a message needs.
	 *
	 * Fields still declaring the older name-keyed array of callables are adapted here, so
	 * they can be moved across one at a time.
	 */
	public function constraints(): Constraint\Set
	{
		$constraints = new Constraint\Set();

		foreach ($this->getConstraints() as $name => $check) {
			$constraints = $constraints->and($name, $check(...));
		}

		return $constraints;
	}



	/**
	 * Converts the raw value given into a Property\Value instance.
	 *
	 * This is where you can implement any custom logic to transform the input value
	 * into a format that is suitable for the field. For example, the composite field
	 * will take a single `null` value and convert it into an array of field name to
	 * value mappings, with all values set to `null`.
	 *
	 * @param AcceptedType|null $value
	 */
	protected function process($value): Property\Value
	{
		return new Property\Value($value);
	}

	/**
	 * The value in whatever type this field is really about — a `BigDecimal` for money, a
	 * `LocalDate` for a date, a parsed phone number. Read through
	 * {@see ResolvedField::$transformed}, and only ever called with a value that already
	 * passed validation.
	 *
	 * The default is the identity: a field that has no richer type to offer says so by not
	 * overriding this. Types are filled in per field from 2.1 onwards; the parsing already
	 * happens inside the constraints today and is simply discarded.
	 *
	 * @param AcceptedType $value
	 */
	public function transform(mixed $value): mixed
	{
		return $value;
	}

	/**
	 * Returns an array of constraints that this field should validate against.
	 *
	 * Each constraint is defined as a callable that takes the field's value
	 * and returns true if the constraint is satisfied, false if it fails,
	 * or null if the constraint should be skipped.
	 *
	 * @return array<string, callable(mixed): bool|null>
	 */
	/**
	 * The older name-keyed array of callables. Fields that have moved to {@see self::constraints()}
	 * declare nothing here; this exists so the two can coexist while they move across one at a
	 * time, and goes when the last of them has.
	 *
	 * @return array<string, callable(mixed): (bool|null)>
	 */
	protected function getConstraints(): array
	{
		return [];
	}

	/**
	 * Returns true if the given value is a valid instance of this field's type.
	 */
	abstract public function validateValue(mixed $value): bool;
}
