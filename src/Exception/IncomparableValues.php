<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use Meraki\Schema\Exception;
use InvalidArgumentException;

/**
 * Two values that cannot be put in an order.
 *
 * Raising is `Comparable`'s contract for this, not a shortcoming of it. There is no honest answer
 * to "is this time of day greater than that date", and returning one anyway would make `isAtLeast`
 * quietly wrong rather than loudly unanswerable.
 *
 * In practice a rule never reaches here: `Rule\Condition\Comparison::whyItCouldNeverHold()` reads
 * an expectation through the field it is compared against, so a mismatched bound is refused as an
 * {@see InvalidRule} where the rule is written. This is the backstop for everything else that
 * compares two values directly.
 */
final class IncomparableValues extends InvalidArgumentException implements Exception
{
	public static function money(): self
	{
		return new self('An amount of money can only be ordered against money.');
	}

	/**
	 * Money of one currency against another. Ranking them needs an exchange rate, which is a fact
	 * about a market on a day rather than a property of either amount — so it cannot be had here,
	 * and guessing at one would be worse than refusing.
	 */
	public static function moneyInDifferentCurrencies(string $left, string $right): self
	{
		return new self(sprintf(
			'%s and %s cannot be ordered: ranking them needs an exchange rate, which is not a '
			. 'property of either amount.',
			$left,
			$right,
		));
	}

	public static function timesOfDay(): self
	{
		return new self('A time of day can only be ordered against another time of day.');
	}

	public static function numbers(): self
	{
		return new self('A number can only be ordered against another number.');
	}

	public static function lengthsOfTime(): self
	{
		return new self('A length of time can only be ordered against another length of time.');
	}

	public static function datesAndTimes(): self
	{
		return new self('A date and time can only be ordered against another date and time.');
	}

	public static function calendarDates(): self
	{
		return new self('A calendar date can only be ordered against another calendar date.');
	}

	public static function slots(): self
	{
		return new self('A slot can only be ordered against another slot.');
	}

	/**
	 * A day, a date and time, and a time of day are different kinds of slot. Midnight on the 13th
	 * is not the 13th, and neither comes before the other.
	 */
	public static function slotsOfDifferentTypes(string $left, string $right): self
	{
		return new self(
			"A {$left} slot can only be ordered against another {$left} slot, not a {$right} one.",
		);
	}

	/**
	 * For a value class this library has never heard of, which is the same reason every other
	 * extension point here is open: `$subject` names what refused — "A parcel weight" — and
	 * `$operand` what it would have accepted: "another parcel weight".
	 */
	public static function becauseTheyAreNotAlike(string $subject, string $operand): self
	{
		return new self("{$subject} can only be ordered against {$operand}.");
	}
}
