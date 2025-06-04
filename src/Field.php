<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Property;
use Meraki\Schema\AggregatedValidationResult;
use Meraki\Schema\Field\ValidationResult;
use Meraki\Schema\Field\ConstraintValidationResult;

/**
 * @template AcceptedType of mixed
 */
abstract class Field
{
	/**
	 * The type of the field, which defines the expected data type.
	 *
	 * This is used to validate the input value against the
	 * expected type. The validator function should return true
	 * if the value is of the expected type, or false otherwise.
	 */
	public readonly Property\Type $type;

	/**
	 * The name of the field.
	 *
	 * This is used to identify the field in the schema and
	 * should be unique within a schema.
	 *
	 * @readonly External code should not modify this property
	 */
	public Property\Name $name;

	/**
	 * The input value of the field.
	 *
	 * This property is always set to a Property\Value instance,
	 * and should not be relied on for determining if input was given.
	 * Use the `inputGiven` property to check if input was provided.
	 *
	 * @readonly External code should not modify this property
	 */
	public Property\Value $value;

	/**
	 * The default value of the field.
	 *
	 * This property is used when no input was given.
	 *
	 * @readonly External code should not modify this property
	 */
	public Property\Value $defaultValue;

	/**
	 * The resolved value of the field.
	 *
	 * This is the value that will be used for validation and
	 * further processing. It is either the input value if provided,
	 * or the default value if no input was given. This value always
	 * reflects the value that will be used for validation at any
	 * point in a field's lifecycle. For example, if a field has a
	 * default value given, and no input value given yet, then this
	 * property will contain the default value.
	 *
	 * @readonly External code should not modify this property
	 */
	public Property\Value $resolvedValue;

	/**
	 * Indicates whether input has been given for this field.
	 *
	 * @readonly External code should not modify this property
	 */
	public bool $inputGiven;

	/**
	 * Indicates whether this field requires input.
	 *
	 * @readonly External code should not modify this property
	 */
	public bool $optional;

	public function __construct(
		Property\Type $type,
		Property\Name $name,
	) {
		$this->type = $type;
		$this->name = $name;
		$this->value = $this->process(null);
		$this->defaultValue = $this->process(null);
		$this->inputGiven = false;
		$this->optional = false;

		$this->resolveValue();
	}

	/**
	 * Renames the field to a new name.
	 *
	 * @param Property\Name $name The new name for the field.
	 */
	public function rename(Property\Name $name): static
	{
		$this->name = $name;

		return $this;
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

	/**
	 * Sets the input value for the field.
	 *
	 * @param AcceptedType|null $value
	 */
	public function input($value): static
	{
		$this->inputGiven = true;
		$this->value = $this->process($value);

		$this->resolveValue();

		return $this;
	}

	/**
	 * Sets the default value for the field, which will be used when
	 * no input has been given.
	 *
	 * @param AcceptedType $value
	 */
	public function prefill($value): static
	{
		$this->defaultValue = $this->process($value);

		$this->resolveValue();

		return $this;
	}

	/**
	 * Resolves the value of the field based on the input given.
	 *
	 * If an input value has been provided, it will be used as the
	 * resolved value. Otherwise, the default value will be used.
	 */
	protected function resolveValue(): void
	{
		$this->resolvedValue = $this->valueProvided($this->value) ? $this->value : $this->defaultValue;
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
	 * Validates the field against its type and constraints.
	 *
	 * This method checks if the value provided matches the expected type
	 * and evaluates any constraints defined for the field. If the field is
	 * optional and no value is provided, it skips all constraints. The
	 * value validated is always the resolved value.
	 *
	 * @return AggregatedValidationResult The result of the validation.
	 */
	public function validate(): AggregatedValidationResult
	{
		$value = $this->resolvedValue;
		$valueNotProvided = !$this->valueProvided($value);

		if ($this->optional && $valueNotProvided) {
			return $this->skipAllConstraints();
		}

		if ($valueNotProvided) {
			return new ValidationResult($this, ConstraintValidationResult::fail('type'));
		}

		$typeIsValid = ($this->type->validator)($value->unwrap());

		if ($typeIsValid) {
			$results = [ConstraintValidationResult::pass('type')];

			foreach ($this->evaluateConstraints($value) as $constraintName => $constraintResult) {
				$results[] = match ($constraintResult) {
					true => ConstraintValidationResult::pass($constraintName),
					false => ConstraintValidationResult::fail($constraintName),
					null => ConstraintValidationResult::skip($constraintName),
				};
			}

			return new ValidationResult($this, ...$results);
		}

		$results = [ConstraintValidationResult::fail('type')];

		foreach ($this->getConstraints() as $constraintName => $constraint) {
			$results[] = ConstraintValidationResult::skip($constraintName);
		}

		return new ValidationResult($this, ...$results);
	}

	/**
	 * Evaluates the constraints defined for this field against the provided value.
	 *
	 * This method should be overridden in subclasses to provide specific constraint
	 * evaluation logic. It returns an associative array where keys are constraint names
	 * and values are the results of the evaluation (true, false, or null).
	 *
	 * @return array<string, bool|null>
	 */
	protected function evaluateConstraints(Property\Value $value): array
	{
		$results = [];

		foreach ($this->getConstraints() as $name => $constraint) {
			$results[$name] = call_user_func($constraint, $value->unwrap());
		}

		return $results;
	}

	protected function skipAllConstraints(): ValidationResult
	{
		$constraintValidationResults = array_map(
			fn(string $constraintName): ConstraintValidationResult => ConstraintValidationResult::skip($constraintName),
			array_keys($this->getConstraints()),
		);

		$typeConstraintValidationResult = ConstraintValidationResult::skip('type');

		return new ValidationResult($this, $typeConstraintValidationResult, ...$constraintValidationResults);
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
	 * Returns an array of constraints that this field should validate against.
	 *
	 * Each constraint is defined as a callable that takes the field's value
	 * and returns true if the constraint is satisfied, false if it fails,
	 * or null if the constraint should be skipped.
	 *
	 * @return array<string, callable(mixed): bool|null>
	 */
	abstract protected function getConstraints(): array;
}
