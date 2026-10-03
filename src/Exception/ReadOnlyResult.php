<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use Meraki\Schema\Exception;
use LogicException;

/**
 * Something tried to write to a result.
 *
 * A result is a record of what was decided about one request, and it reads like an array where that
 * helps — `$result->forPart(Address\Part::Country)[0]` — without being one. Writing to it could only
 * ever make the record say something that was not decided, so it is refused outright rather than
 * quietly copied and discarded.
 */
final class ReadOnlyResult extends LogicException implements Exception
{
	public static function cannotBeWrittenTo(string $class): self
	{
		return new self(sprintf(
			'%s is a record of what was decided and cannot be written to. Build the result you want '
			. 'instead of editing this one.',
			$class,
		));
	}
}
