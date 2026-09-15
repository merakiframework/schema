<?php
declare(strict_types=1);

namespace Meraki\Schema\Comparison;

/**
 * Where one value sits relative to another.
 *
 * Three named cases instead of `-1`, `0` and `1`. Everything else in this library that has a
 * closed set of answers says so with an enum — {@see \Meraki\Schema\ValidationStatus},
 * {@see \Meraki\Schema\ValueSource}, {@see \Meraki\Schema\Field\ShapeProblem} — and ordering was
 * the last place still returning a magic integer for a caller to remember the sign convention of.
 *
 * It reads at the call site, which is the point:
 *
 *     $a->compareTo($b)->isAtLeast()      // rather than  $a->compareTo($b) >= 0
 *
 * Backed by the conventional integers so {@see self::of()} can take whatever a third-party
 * comparison returned, and so the cases sort correctly if anyone ever puts them in order.
 */
enum Order: int
{
	case Less = -1;
	case Equal = 0;
	case Greater = 1;

	/**
	 * Reads a conventional comparison integer.
	 *
	 * The spaceship against zero is what makes this total: `strcmp()` and several of Brick's
	 * comparisons are documented only as "negative, zero or positive" and are free to return `-42`,
	 * so normalising is not defensive — it is reading the contract as written.
	 */
	public static function of(int $comparison): self
	{
		return self::from($comparison <=> 0);
	}

	public function isLess(): bool
	{
		return $this === self::Less;
	}

	public function isEqual(): bool
	{
		return $this === self::Equal;
	}

	public function isGreater(): bool
	{
		return $this === self::Greater;
	}

	/** Greater or equal — what a `isAtLeast` matcher asks. */
	public function isAtLeast(): bool
	{
		return $this !== self::Less;
	}

	/** Less or equal — what a `isAtMost` matcher asks. */
	public function isAtMost(): bool
	{
		return $this !== self::Greater;
	}

	/**
	 * The same comparison read from the other side, so `$a->compareTo($b)->flipped()` is
	 * `$b->compareTo($a)` without asking twice.
	 */
	public function flipped(): self
	{
		return self::from(-$this->value);
	}
}
