<?php
declare(strict_types=1);

namespace Meraki\Schema\Sanitizer;

use Meraki\Schema\Attribute\Value;
use Meraki\Schema\FieldSanitizer;

/**
 * Remove characters from a value
 */
final class RemoveCharacters implements FieldSanitizer
{
	public function __construct(public readonly array $charactersToRemove)
	{
		if (count($charactersToRemove) === 0) {
			throw new \InvalidArgumentException('Characters to remove must not be empty.');
		}
	}

	public function sanitize(Value $value): Value
	{
		if (!is_string($value->value)) {
			return $value;
		}

		return new Value(str_replace($this->charactersToRemove, '', $value->value));
	}
}
