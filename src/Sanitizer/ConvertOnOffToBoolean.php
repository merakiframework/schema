<?php
declare(strict_types=1);

namespace Meraki\Schema\Sanitizer;

use Meraki\Schema\Attribute\Value;
use Meraki\Schema\FieldSanitizer;

/**
 * Convert the string values "on" and "off" to boolean values.
 */
final class ConvertOnOffToBoolean implements FieldSanitizer
{
	public function sanitize(Value $value): Value
	{
		return match (strtolower($value->value)) {
			'on' => Value::of(true),
			'off' => Value::of(false),
			default => $value,
		};
	}
}
