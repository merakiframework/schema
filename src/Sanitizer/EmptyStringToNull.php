<?php
declare(strict_types=1);

namespace Meraki\Schema\Sanitizer;

use Meraki\Schema\Attribute\Value;
use Meraki\Schema\FieldSanitizer;

/**
 * Convert an empty string "" to null. Useful if you want to consider empty input as no input given.
 */
final class EmptyStringToNull implements FieldSanitizer
{
	public function sanitize(Value $value): Value
	{
		if ($value->value === '') {
			return Value::of(null);
		}

		return $value;
	}
}
