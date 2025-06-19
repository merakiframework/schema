<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;

/**
 * @phpstan-import-type SerializedField from Field
 * @phpstan-type SerializedBoolean = SerializedField&object{
 * 	type: 'boolean',
 * 	value: bool|null
 * }
 * @extends AtomicField<bool|null, SerializedBoolean>
 */
final class Boolean extends AtomicField
{
	public function __construct(
		Property\Name $name,
	) {
		parent::__construct(new Property\Type('boolean', $this->validateType(...)), $name);
	}

	protected function cast(mixed $value): bool
	{
		return $value;
		// return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
		// 	?? throw new \InvalidArgumentException('Invalid boolean value: ' . $value);
	}

	protected function validateType(mixed $value): bool
	{
		return is_bool($value);
	}

	protected function getConstraints(): array
	{
		return [];
	}

	/**
	 * @return SerializedBoolean
	 */
	public function serialize(): object
	{
		return (object)[
			'type' => $this->type->value,
			'name' => $this->name->value,
			'optional' => $this->optional,
			'value' => $this->defaultValue->unwrap(),
			'fields' => [],
		];
	}

	/**
	 * @param SerializedBoolean $name
	 */
	public static function deserialize(object $serialized): static
	{
		if ($serialized->type !== 'boolean') {
			throw new \InvalidArgumentException('Invalid type for Boolean field: ' . $serialized->type);
		}

		$field = new self(new Property\Name($serialized->name));
		$field->optional = $serialized->optional;
		$field->prefill($serialized->value);

		return $field;
	}
}
