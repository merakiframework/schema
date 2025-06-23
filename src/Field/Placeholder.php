<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;
use InvalidArgumentException;

/**
 * @phpstan-import-type SerializedField from Field
 * @phpstan-type SerializedPlaceholder = SerializedField&object{
 * 	type: 'placeholder'
 * }
 * @extends AtomicField<mixed|null, SerializedPlaceholder>
 */
final class Placeholder extends AtomicField
{
	public function __construct(
		Property\Name $name,
	) {
		parent::__construct(new Property\Type('placeholder', $this->validateType(...)), $name);
	}

	protected function validateType(mixed $value): bool
	{
		return is_string($value);
	}

	protected function getConstraints(): array
	{
		return [];
	}

	/**
	 * @return SerializedPlaceholder
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
	 * @param SerializedPlaceholder $serialized
	 */
	public static function deserialize(object $serialized, Field\Factory $fieldFactory): static
	{
		if ($serialized->type !== 'placeholder') {
			throw new InvalidArgumentException('Invalid type for Placeholder field.');
		}

		$field = new static(new Property\Name($serialized->name));
		$field->optional = $serialized->optional;

		return $field->prefill($serialized->value);
	}
}
