<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Type;

use Meraki\Schema\Field\Type;
use Meraki\Schema\Validator\CheckType;

/**
 * A "phone number" field type is used to represent an international or national phone number.
 *
 * It conforms to the E.164 format for international phone numbers:
 *  - starts with a '+' followed by the country code and the subscriber number.
 *  - cannot have any special characters proceeding or surrounding the country code.
 *  - can include spaces, dashes, periods, and parentheses for formatting.
 *  - cannot contain any other characters.
 *  - must be between 2 and 15 digits long.
 */
final class PhoneNumber implements Type
{
	private const PATTERN = '/^\+\d{1,3}[\ \-\.\(\)]*(?:\d[\ \-\.\(\)]*)*$/';
	private const ALLOWED_FORMATTING_CHARACTERS = [' ', '-', '.', '(', ')'];

	public string $name = 'phone_number';

	public function accepts(mixed $value): bool
	{
		if (is_string($value)) {
			$valueWithoutFormattingChars = preg_replace('/[\+\ \-\.\(\)]/', '', $value);

			return preg_match(self::PATTERN, $value) === 1
				&& mb_strlen($valueWithoutFormattingChars) >= 2
				&& mb_strlen($valueWithoutFormattingChars) <= 15;
		}

		return false;
	}

	public function getValidator(): CheckType
	{
		return new CheckType($this);
	}
}


// class PhoneNumber extends Field
// {

// 	public static function getSupportedAttributes(): array
// 	{
// 		return Attribute\Set::ALLOW_ALWAYS_SUPPORTED_ONLY;
// 	}
// }
