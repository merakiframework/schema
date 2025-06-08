<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field\Serialized;
use Meraki\Schema\Property;

/**
 * @extends Serialized<bool|null>
 * @internal
 */
interface SerializedBoolean extends Serialized
{
}

/**
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

	public function serialize(): SerializedBoolean
	{
		return new class(
			type: $this->type->value,
			name: $this->name->value,
			optional: $this->optional,
			value: $this->defaultValue->unwrap(),
		) implements SerializedBoolean {
			public function __construct(
				public readonly string $type,
				public readonly string $name,
				public readonly bool $optional,
				public readonly ?bool $value,
			) {}

			public function getConstraints(): array
			{
				return [];
			}

			public function children(): array
			{
				return [];
			}
		};
	}

	/**
	 * @param SerializedBoolean $name
	 */
	public static function deserialize(Serialized $serialized): static
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
