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

	public function __construct(Property\Type $type, Property\Name $name, AtomicField ...$fields)
	{
		$this->fields = new Field\Set(...$fields);
		$this->fields->prefixNamesWith($name);

		parent::__construct($type, $name);
	}

	public function rename(Property\Name $name): static
	{
		$this->name = $name;
		$this->fields->prefixNamesWith($name);

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
		if (($this->optional && !$this->valueProvided($value)) || !($this->type->validator)($value->unwrap())) {
			return $this->validationResult = $this->skipValidationOfAllFields();
		}

		// First validate types of each subfield
		foreach ($this->fields as $field) {
			$fieldName = (string)$field->name;
			$result = ($field->type->validator)($field->resolvedValue->unwrap());

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

			// Skip constraint if the field failed/skipped type validation
			if (isset($fieldsToSkip[$fieldName]) || ($field->optional && !$this->valueProvided($field->resolvedValue))) {
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
				if (isset($fieldsToSkip[$fieldName]) || ($field->optional && !$this->valueProvided($field->resolvedValue))) {
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

		return $this->validationResult = new CompositeValidationResult($this, ...array_values($fieldResults));
	}

	/**
	 * Resolves the constraint name to the corresponding field name.
	 *
	 * All constraint names in this class are expected to be prefixed with the field name.
	 */
	private function resolveConstraintNameToFieldName(string $constraintName): string
	{
		$parts = explode('.', $constraintName, 3);

		if (count($parts) < 3) {
			throw new InvalidArgumentException("Invalid constraint name: '$constraintName'. Expected format is 'compositeFieldName.subFieldName.constraintName'.");
		}

		return $parts[0] . '.' . $parts[1];
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

	protected function validateType(mixed $value): bool
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
		}

		if (!is_array($value)) {
			throw new InvalidArgumentException('Input value must be an array or null.');
		}

		foreach ($this->fields as $field) {
			$fieldName = (string)$field->name;

			if (!isset($value[$fieldName])) {
				$value[$fieldName] = null;
			}
		}

		return new Property\Value($value);
	}
}
