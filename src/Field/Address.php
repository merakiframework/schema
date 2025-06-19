<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Composite as CompositeField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;
use InvalidArgumentException;

/**
 * A field representing an address.
 *
 * @todo Maybe add line1 (person) and line2 (company) fields
 *
 * @phpstan-import-type SerializedField from Field
 * @phpstan-type SerializedAddress = SerializedField&object{
 * 	type: 'address',
 * 	value: array|null,
 * }
 * @extends CompositeField<array|null, SerializedAddress>
 *
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

	/**
	 * @return SerializedAddress
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
		// $serializedChildren = array_map(
		// 	fn(Field $field): Serialized => $field->serialize(),
		// 	$this->fields->getIterator()->getArrayCopy()
		// );
		// return new class(
		// 	type: $this->type->value,
		// 	name: $this->name->value,
		// 	optional: $this->optional,
		// 	value: $this->defaultValue->unwrap(),
		// 	fields: $serializedChildren
		// ) implements SerializedAddress {
		// 	/**
		// 	 * @param array<Serialized> $fields
		// 	 */
		// 	public function __construct(
		// 		public readonly string $type,
		// 		public readonly string $name,
		// 		public readonly bool $optional,
		// 		public readonly array $value,
		// 		public readonly array $fields,
		// 	) {}
		// };
	}

	/**
	 * @param SerializedAddress $serialized
	 */
	public static function deserialize(object $serialized, Field\Factory $fieldFactory = new Field\Factory()): static
	{
		if ($serialized->type !== 'address') {
			throw new InvalidArgumentException('Invalid serialized type for Address field.');
		}

		$deserializedChildren = array_map($fieldFactory->deserialize(...), $serialized->fields);
		$field = new self(new Property\Name($serialized->name));
		$field->optional = $serialized->optional;
		$field->fields = new Field\Set(...$deserializedChildren);

		return $field->prefill($serialized->value);
	}
}
