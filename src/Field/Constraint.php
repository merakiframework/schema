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
	 */
	public function __construct(
		public string $name,
		public Closure $check,
		public string|int|float|bool|array|null $bound = null,
		public ?string $part = null,
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
		return match (($this->check)($value)) {
			true => ConstraintValidationResult::pass($this->name, $this->part, $this->bound),
			false => ConstraintValidationResult::fail($this->name, $this->part, $this->bound),
			default => $this->skipped(),
		};
	}

	/**
	 * Reported when there was nothing to check — either the constraint said so, or the value
	 * never got far enough to be judged.
	 */
	public function skipped(): ConstraintValidationResult
	{
		return ConstraintValidationResult::skip($this->name, $this->part, $this->bound);
	}
}
