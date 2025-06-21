<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;

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
 * @phpstan-import-type SerializedField from Field
 * @phpstan-type SerializedPhoneNumber = SerializedField&object{
 * 	type: 'phone_number'
 * }
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

	/**
	 * @return SerializedPhoneNumber
	 */
	public function serialize(): object
	{
		return (object)[
			'type' => $this->type->value,
			'name' => $this->name->value,
			'optional' => $this->optional,
			'value' => $this->defaultValue->unwrap() !== null ? $this->cast($this->defaultValue->unwrap()) : null,
			'fields' => [],
		];
	}

	/**
	 * @param SerializedPhoneNumber $data
	 */
	public static function deserialize(object $data, Field\Factory $fieldFactory): static
	{
		if ($data->type !== 'phone_number') {
			throw new \InvalidArgumentException('Invalid serialized data for PhoneNumber.');
		}

		$field = new self(new Property\Name($data->name));
		$field->optional = $data->optional;

		return $field->prefill($data->value);
	}
}
