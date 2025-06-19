<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;

/**
 * @template T of scalar
 * @phpstan-import-type SerializedField from Field
 * @phpstan-type SerializedEnum = SerializedField&object{
 * 	type: 'enum',
 * 	value: string|null,
 * 	one_of: list<T>,
 * }
 * @extends AtomicField<string|null, SerializedEnum>
 */
final class Enum extends AtomicField
{
	public function __construct(
		Property\Name $name,
		/**
		 * @readonly
		 * @param list<T> $oneOf
		 */
		public array $oneOf,
	) {
		parent::__construct(new Property\Type('enum', $this->validateType(...)), $name);
	}

	public function allow(mixed $value): self
	{
		if (!in_array($value, $this->oneOf, true)) {
			$this->oneOf[] = $value;
		}

		return $this;
	}

	protected function validateType(mixed $value): bool
	{
		return in_array($value, $this->oneOf, true);
	}

	protected function getConstraints(): array
	{
		return [];
	}

	/**
	 * @return SerializedEnum
	 */
	public function serialize(): object
	{
		return (object)[
			'type' => $this->type->value,
			'name' => $this->name->value,
			'optional' => $this->optional,
			'value' => $this->defaultValue->unwrap(),
			'fields' => [],
			'one_of' => $this->oneOf,
		];
	}

	/**
	 * @param SerializedEnum $serialized
	 */
	public static function deserialize(object $serialized): static
	{
		if ($serialized->type !== 'enum') {
			throw new \InvalidArgumentException('Invalid serialized data for Enum.');
		}

		$enumField = new self(
			new Property\Name($serialized->name),
			$serialized->one_of
		);

		$enumField->optional = $serialized->optional;

		return $enumField->prefill($serialized->value);
	}
}
