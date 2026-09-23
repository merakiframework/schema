<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use InvalidArgumentException;
use Meraki\Schema\Exception;

/**
 * A constraint that cannot be reported.
 *
 * Every one of these protects the same promise: a field reports each constraint under a name, and
 * a consumer looks the result up by it. A nameless or repeated name breaks the lookup rather than
 * the check — the constraint would still run, and its answer would be unreachable or would quietly
 * stand in for another's.
 */
final class InvalidConstraint extends InvalidArgumentException implements Exception
{
	public static function mustBeNamed(): self
	{
		return new self('A constraint must be named.');
	}

	public static function partCannotBeEmpty(): self
	{
		return new self('A constraint\'s part cannot be empty; use null for the whole value.');
	}

	public static function nameIsUsedTwice(string $name): self
	{
		return new self(sprintf(
			'Duplicate constraint "%s": a field reports each name once, so a result can be looked up by it.',
			$name,
		));
	}

	public static function nameIsUsedTwiceOnAField(string $name, string $field): self
	{
		return new self(sprintf('Duplicate constraint name "%s" on field "%s".', $name, $field));
	}

	public static function lookedUpWithNoName(): self
	{
		return new self('Constraint name cannot be empty.');
	}
}
