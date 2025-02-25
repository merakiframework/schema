<?php
declare(strict_types=1);

namespace Meraki\Schema\Sanitizer;

use Meraki\Schema\Attribute\Value;
use Meraki\Schema\FieldSanitizer;

/**
 * Convert a number represented in a string to a number type.
 */
final class CoerceStringToNumber implements FieldSanitizer
{
	private const REGEX = '/^[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?$/';

	public function sanitize(Value $value): Value
	{
		if (is_string($value->value) && preg_match(self::REGEX, $value->value)) {
			// If all characters are digits (including -/+ sign), it's an integer
			if (ctype_digit($value->value)
				|| ((str_starts_with($value->value, '-') || str_starts_with($value->value, '+')) && ctype_digit(substr($value->value, 1)))) {
				return Value::of((integer)$value->value);
			}

			// $number = ltrim($value->value, '+');

			// // handle edge case where a string is a positive number, but has a leading zero,
			// // which would cause PHP to interpret it as an octal number
			// if (str_starts_with($number, '0') && ctype_digit($number)) {
			// 	return Value::of((float)octdec($number));
			// }

			return Value::of((float)$value->value);
		}

		return $value;
	}
}
