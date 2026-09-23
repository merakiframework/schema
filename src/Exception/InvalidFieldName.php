<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use InvalidArgumentException;
use Meraki\Schema\Exception;

/**
 * A name a field cannot be identified by.
 *
 * Raised where the name is written, which is the only place it can be: a name identifies a field
 * within a schema and in every scope that addresses it, so one that cannot be written down has
 * nothing downstream that could report it.
 */
final class InvalidFieldName extends InvalidArgumentException implements Exception
{
	public static function isEmpty(): self
	{
		return new self('A name cannot be empty.');
	}

	public static function isNotUsable(string $name): self
	{
		return new self(sprintf(
			'"%s" is not a usable name: each part must start with a letter or '
			. 'underscore and contain only letters, digits, underscores and hyphens.',
			$name,
		));
	}
}
