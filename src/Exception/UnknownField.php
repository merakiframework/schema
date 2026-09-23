<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use InvalidArgumentException;
use Meraki\Schema\Exception;

/**
 * A field asked for by a name the schema does not hold.
 *
 * Separate from {@see DuplicateFieldName}, which is the opposite mistake, because a caller
 * catching one almost never wants the other: "look this up and tell me if it is missing" and "add
 * this and tell me if it is already here" are different questions asked at different times.
 *
 * `Field\Set::findByName()` is the nullable reading for when absence is an ordinary answer. This
 * is for the readings that promise a field — `getByName()`, and a rule naming one.
 */
final class UnknownField extends InvalidArgumentException implements Exception
{
	public static function named(string $name): self
	{
		return new self(sprintf('Field with name "%s" does not exist.', $name));
	}

	public static function cannotBeReplaced(string $name): self
	{
		return new self(sprintf(
			'No field named "%s" to replace. A field is added before anything can change it.',
			$name,
		));
	}
}
