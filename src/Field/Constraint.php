<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Closure;
use InvalidArgumentException;

/**
 * One check a field makes, and everything a consumer needs to report it.
 *
 * A constraint used to be a bare callable keyed by name, which meant a result could say only
 * *that* something failed. Anything wanting to say more — which part of a structured value,
 * or what limit was missed — had to go back to the field and guess, by splitting the name on
 * dots or by reading `$field->{$name}`. Both are gone; the constraint states it.
 *
 * Some limits only exist once you know what was submitted — Money holds a minimum per currency,
 * Address a postcode pattern per country — so "the minimum" has no single answer until an amount
 * arrives naming one. {@see self::$boundFor} covers those. Declaring {@see self::$bound} alone
 * left them reporting null whenever more than one currency or country was allowed, so a message
 * could say a value was too small but not what it should have reached.
 */
final readonly class Constraint
{
	/**
	 * @param string $name what is checked, and the name a failure is reported under
	 * @param Closure(mixed): (bool|null) $check true passes, false fails, null skips
	 * @param string|int|float|bool|list<string>|null $bound the limit that applies, ready for
	 *        a message; null when there is nothing to interpolate, as for a PO-box check
	 * @param string|null $part the part of a structured value this concerns, e.g. `postalCode`;
	 *        null when it concerns the whole value
	 * @param Closure(mixed): (string|int|float|bool|list<string>|null)|null $boundFor the bound
	 *        that applies to a *particular* value, for a limit that only exists once you know what
	 *        was submitted
	 */
	public function __construct(
		public string $name,
		public Closure $check,
		public string|int|float|bool|array|null $bound = null,
		public ?string $part = null,
		public ?Closure $boundFor = null,
		/**
		 * Whether the answer depends on *when* it is asked.
		 *
		 * "Has this card expired" is true or false depending on the calendar, and nothing about
		 * the field or the value changes between two readings that disagree. Every other
		 * constraint here is a question about the value alone.
		 *
		 * It matters in one place: an authored default is checked where it is written, which
		 * cannot hold for a question like this. A default valid the day the schema was built
		 * would start throwing years later with nothing having been edited — at boot, inside a
		 * constructor, with no request to blame. So these are exempt from that check and are
		 * judged per request like any submitted value.
		 */
		public bool $timeRelative = false,
	) {
		if ($name === '') {
			throw new InvalidArgumentException('A constraint must be named.');
		}

		if ($part === '') {
			throw new InvalidArgumentException('A constraint\'s part cannot be empty; use null for the whole value.');
		}
	}

	/**
	 * Runs the check and reports it, carrying the part and bound through so the caller does
	 * not have to know them.
	 */
	public function against(mixed $value): ConstraintValidationResult
	{
		$bound = $this->boundFor === null ? $this->bound : ($this->boundFor)($value);

		return match (($this->check)($value)) {
			true => ConstraintValidationResult::pass($this->name, $this->part, $bound),
			false => ConstraintValidationResult::fail($this->name, $this->part, $bound),
			default => ConstraintValidationResult::skip($this->name, $this->part, $bound),
		};
	}

	/**
	 * Reported when the value never got far enough to be judged — it could not be read at all, so
	 * no constraint ran.
	 *
	 * Carries the *declared* bound only. A bound that depends on the submitted value cannot be
	 * resolved when there is no usable value to resolve it against, and inventing one would be
	 * worse than reporting none.
	 */
	public function skipped(): ConstraintValidationResult
	{
		return ConstraintValidationResult::skip($this->name, $this->part, $this->bound);
	}
}
