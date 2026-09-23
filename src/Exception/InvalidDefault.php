<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use InvalidArgumentException;
use Meraki\Schema\Exception;
use Throwable;

/**
 * A default the field that declares it could never hold.
 *
 * Raised where the default is *written*, which is the point of it: an authored default is trusted
 * by construction rather than by assumption, so a request never has to re-check it. Failing at
 * boot, naming the field, beats a value that silently prefills every form with something the field
 * would reject.
 *
 * Time-relative constraints are exempt and judged per request instead — their answer changes
 * without the schema changing, so a default that passes today would start throwing on its own
 * years later with nothing having been edited.
 */
final class InvalidDefault extends InvalidArgumentException implements Exception
{
	/**
	 * Not absorbed, unlike the request path: an author can act on *why* their default is
	 * unreadable, so the malformed value's own sentence is carried through.
	 */
	public static function isNotAValueTheFieldCanHold(string $field, Throwable $why): self
	{
		return new self(
			sprintf('The default for "%s" is not a value it can hold. %s', $field, $why->getMessage()),
			previous: $why,
		);
	}

	public static function failsItsOwnConstraint(string $field, string $constraint): self
	{
		return new self(sprintf(
			'The default for "%s" does not satisfy its own "%s" constraint.',
			$field,
			$constraint,
		));
	}
}
