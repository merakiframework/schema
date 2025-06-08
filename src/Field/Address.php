<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use InvalidArgumentException;
use Meraki\Schema\Field;
use Meraki\Schema\Field\Composite as CompositeField;
use Meraki\Schema\Property;

/**
 * @extends Serialized<array>
 * @internal
 */
interface SerializedAddress extends Serialized
{
}

/**
 * A field representing an address.
 *
 * @todo Maybe add line1 (person) and line2 (company) fields
 *
 * @extends CompositeField<array|null, SerializedAddress>
 * @property-read Field\Text $street
 * @property-read Field\Text $city
 * @property-read Field\Text $state
 * @property-read Field\Text $postalCode
 * @property-read Field\Text $country
 */
final class Address extends CompositeField
{
	public function __construct(Property\Name $name)
	{
		parent::__construct(
			new Property\Type('address', $this->validateAddressType(...)),
			$name,
			new Field\Text(new Property\Name('street')),
			new Field\Text(new Property\Name('city')),
			new Field\Text(new Property\Name('state')),
			new Field\Text(new Property\Name('postal_code')),
			new Field\Text(new Property\Name('country')),
		);
	}

	protected function getConstraints(): array
	{
		return [];
	}

	private function validateAddressType(mixed $value): bool
	{
		return true;
	}

	public function serialize(): SerializedAddress
	{
		$serializedChildren = array_map(
			fn(Field $field): Serialized => $field->serialize(),
			$this->fields->getIterator()->getArrayCopy()
		);
		return new class(
			type: $this->type->value,
			name: $this->name->value,
			optional: $this->optional,
			value: $this->defaultValue->unwrap(),
			children: $serializedChildren
		) implements SerializedAddress {
			/**
			 * @param array<Serialized> $children
			 */
			public function __construct(
				public readonly string $type,
				public readonly string $name,
				public readonly bool $optional,
				public readonly array $value,
				private array $children,
			) {}

			public function getConstraints(): array
			{
				return [];
			}

			public function children(): array
			{
				return $this->children;
			}
		};
	}

	/**
	 * @param SerializedAddress $serialized
	 */
	public static function deserialize(Serialized $serialized): static
	{
		if ($serialized->type !== 'address') {
			throw new InvalidArgumentException('Invalid serialized type for Address field.');
		}

		$deserializedChildren = [];

		foreach ($serialized->children() as $child) {
			$deserializedChildren[] = match ($child->type) {
				'text' => Field\Text::deserialize($child),
				'enum' => Field\Enum::deserialize($child),
				default => throw new InvalidArgumentException("Unsupported child type: {$child->type}"),
			};
		}

		$field = new self(new Property\Name($serialized->name));
		$field->optional = $serialized->optional;
		$field->fields = new Field\Set(...$deserializedChildren);

		return $field->prefill($serialized->value);
	}
}
