<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\ValidationResult;
use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field;
use Meraki\Schema\ValidationStatus;
use Meraki\Schema\Property;
use Meraki\Schema\AggregatedValidationResult;
use InvalidArgumentException;

/**
 * A variant field is a field that can have multiple types. It is used to represent a value that can be one of several different types.
 * For example, a variant field can be used to represent a password type and a passphrase type.
 * A variant field can only contain atomic fields, which are fields that have a single value.
 * A variant field cannot use the same field type more than once.
 * Each field in a variant must have a unique name, and the names are prefixed with the variant's name.
 * The order that fields are added is the order that they are validated.
 * The first field that matches the value is the one that is used. (e.g. if a value can match a password and a passphrase field, but
 * the passphrase field was added first, then the passphrase field result is returned.)
 *
 * @phpstan-import-type SerializedField from Field
 * @phpstan-type SerializedVariant = SerializedField&object{
 * 	type: 'variant'
 * }
 * @template AcceptedType of mixed
 * @extends Field<AcceptedType|null, SerializedVariant>
 */
final class Variant extends Field
{
	public Field\Set $fields;

	public ?AtomicField $matchedField = null;

	public function __construct(
		Property\Name $name,
		AtomicField ...$fields
	) {
		parent::__construct(new Property\Type('variant', $this->validateType(...)), $name);

		$this->fields = new Field\Set(...$fields);

		if ($this->fields->containsDuplicateFieldTypes()) {
			throw new InvalidArgumentException('Variant fields cannot contain duplicate field types.');
		}

		$this->rename($name);
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

		foreach ($this->fields as $field) {
			try {
				$field->prefill($value);
			} catch (InvalidArgumentException $e) {
				continue;
			}
		}

		return $this;
	}

	/** @param AcceptedType $value */
	public function input($value): static
	{
		parent::input($value);

		foreach ($this->fields as $field) {
			$field->input($value);
		}

		return $this;
	}

	public function validate(): AggregatedValidationResult
	{
		$value = $this->resolvedValue;

		// if the field is optional and no value is provided, skip all constraints
		if ($this->optional && $value->notProvided()) {
			return new ValidationResult($this, ConstraintValidationResult::skip('type'));
		}

		// if the field is not optional and no value provided, return a validation error
		if ($value->notProvided()) {
			return new ValidationResult($this, ConstraintValidationResult::fail('type'));
		}

		$typeIsValid = ($this->type->validator)($value->unwrap());
		$fieldResults = [];

		if ($typeIsValid) {
			foreach ($this->fields as $field) {
				$result = $field->validate();

				if ($result->status === ValidationStatus::Passed) {
					$this->matchedField = $field;
					$this->resolvedValue = $field->resolvedValue;
					return $result;
				}

				$fieldResults[] = $result;
			}
		}

		return new CompositeValidationResult($this, ...$fieldResults);
	}

	public function getConstraints(): array
	{
		return [];
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
	 * @return SerializedVariant
	 */
	public function serialize(): object
	{
		return (object)[
			'type' => $this->type->value,
			'name' => $this->name->value,
			'optional' => $this->optional,
			'value' => $this->defaultValue->unwrap(),
			'fields' => array_map(
				fn(Field $field): Serialized => $field->serialize(),
				$this->fields->getIterator()->getArrayCopy()
			),
		];
	}

	/** @param SerializedVariant $serialized */
	public static function deserialize(object $serialized, Field\Factory $fieldFactory): static
	{
		if ($serialized->type !== 'variant') {
			throw new InvalidArgumentException('Invalid type for Variant field: ' . $serialized->type);
		}

		$deserializedChildren = array_map($fieldFactory->deserialize(...), $serialized->fields);
		$field = new self(new Property\Name($serialized->name), ...$deserializedChildren);
		$field->optional = $serialized->optional;

		return $field->prefill($serialized->value);
	}
}
