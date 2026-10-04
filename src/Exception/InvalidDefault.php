<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use Meraki\Schema\Exception;
use Meraki\Schema\Field\Violation;
use InvalidArgumentException;
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

	/**
	 * The default is a record whose parts make no value: a currency with no amount, say.
	 *
	 * Each problem is named with the part it is about, because that is what the author has to
	 * edit, and the codes are the ones a request would have been told.
	 *
	 * @param non-empty-list<Violation> $violations
	 */
	public static function isNotAWholeValue(string $field, array $violations): self
	{
		return new self(sprintf(
			'The default for "%s" does not make a whole value: %s.',
			$field,
			implode(', ', array_map(
				static fn(Violation $violation): string => $violation->part === null
					? sprintf('"%s"', $violation->name)
					: sprintf('"%s" on its %s', $violation->name, (string) $violation->part->value),
				$violations,
			)),
		));
	}

	/**
	 * The default is a record with no part in it. A request reads that as nothing submitted, so
	 * as a default it would stand in for nothing with nothing — and keep the author from noticing
	 * that whatever built it left every part out.
	 */
	public static function saysNothing(string $field): self
	{
		return new self(
			"The default for \"{$field}\" is a record with no part in it, which reads as nothing submitted. "
			. 'Leave the default out, or give it its parts.',
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
