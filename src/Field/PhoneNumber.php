<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Property;

/**
 * @extends Serialized<string|null>
 * @internal
 */
interface SerializedPhoneNumber extends Serialized
{
}

/**
 * A "phone number" field type is used to represent an international or national phone number.
 *
 * It conforms to the E.164 format for international phone numbers:
 *  - starts with a '+' followed by the country code and the subscriber number.
 *  - cannot have any special characters proceeding or surrounding the country code.
 *  - can include spaces, dashes, periods, and parentheses for formatting.
 *  - cannot contain any other characters.
 *  - must be between 2 and 15 digits long.
 *
 * @extends AtomicField<string|null, SerializedPhoneNumber>
 */
final class PhoneNumber extends AtomicField
{
	private const PATTERN = '/^\+\d{1,3}[\ \-\.\(\)]*(?:\d[\ \-\.\(\)]*)*$/';
	private const ALLOWED_FORMATTING_CHARACTERS = [' ', '-', '.', '(', ')'];

	public function __construct(
		Property\Name $name,
	) {
		parent::__construct(new Property\Type('phone_number', $this->validateType(...)), $name);
	}

	protected function cast(string $value): string
	{
		return preg_replace('/[^\d\+]/', '', $value);
	}

	protected function validateType(mixed $value): bool
	{
		if (!is_string($value)) {
			return false;
		}

		if (preg_match(self::PATTERN, $value) === 1) {
			$value = $this->cast($value);

			return mb_strlen($value) > 2		// account for leading '+', country code, and subscriber number
				&& mb_strlen($value) < 17;		// allow up to 15 digits, plus the leading '+'
		}

		return false;
	}

	protected function getConstraints(): array
	{
		return [];
	}

	public function serialize(): SerializedPhoneNumber
	{
		return new class(
			type: $this->type->value,
			name: $this->name->value,
			optional: $this->optional,
			value: $this->value !== null ? $this->cast($this->defaultValue->unwrap()) : null,
		) implements SerializedPhoneNumber {
			public function __construct(
				public readonly string $type,
				public readonly string $name,
				public readonly bool $optional,
				public ?string $value = null,
			) {}
			public function getConstraints(): array
			{
				return [];
			}
		};
	}

	/**
	 * @param SerializedPhoneNumber $data
	 */
	public static function deserialize(Serialized $data): static
	{
		if ($data->type !== 'phone_number') {
			throw new \InvalidArgumentException('Invalid serialized data for PhoneNumber.');
		}

		$field = new self(new Property\Name($data->name));
		$field->optional = $data->optional;

		return $field->prefill($data->value);
	}
}
