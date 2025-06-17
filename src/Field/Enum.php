<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Property;

/**
 * @extends Serialized<string|null>
 * @template T of scalar
 * @property-read list<T> $one_of
 * @internal
 */
interface SerializedEnum extends Serialized
{
}

/**
 * @template T of scalar
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

	public function serialize(): SerializedEnum
	{
		return new class(
			type: $this->type->value,
			name: $this->name->value,
			optional: $this->optional,
			value: $this->defaultValue->unwrap(),
			one_of: $this->oneOf,
			fields: [],
		) implements SerializedEnum {
			public function __construct(
				public readonly string $type,
				public readonly string $name,
				public readonly bool $optional,
				public readonly string|null $value,
				/** @param list<T> $one_of */
				public readonly array $one_of,
				/** @param array<Serialized> $fields */
				public readonly array $fields,
			) {
			}
		};
	}

	/**
	 * @param SerializedEnum $serialized
	 */
	public static function deserialize(Serialized $serialized): static
	{
		if ($serialized->type !== 'enum' || !($serialized instanceof SerializedEnum)) {
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
