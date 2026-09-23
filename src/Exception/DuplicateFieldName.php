<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use InvalidArgumentException;
use Meraki\Schema\Exception;

/**
 * Two fields under one name.
 *
 * Names identify fields — in the set, in every scope that addresses one, and in submitted input —
 * so a second field under an existing name is a mistake in the schema definition rather than a
 * choice between them. Silently discarding one loses a definition and gives no clue where it went.
 *
 * The opposite of {@see UnknownField}, and kept apart from it because a caller catching one almost
 * never wants the other.
 */
final class DuplicateFieldName extends InvalidArgumentException implements Exception
{
	public static function named(string $name): self
	{
		return new self(sprintf('A field named "%s" already exists.', $name));
	}
}
