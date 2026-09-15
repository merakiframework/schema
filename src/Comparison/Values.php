<?php
declare(strict_types=1);

namespace Meraki\Schema\Comparison;

/**
 * The one place this library answers "are these two things the same value".
 *
 * Two callers need that answer and must not disagree about it: {@see \Meraki\Schema\Field\Collection\Value},
 * deciding whether two rows repeat, and {@see \Meraki\Schema\Rule\Condition\Comparison}, deciding
 * whether a field holds what a rule is asking about. Written twice they would drift, and a rule
 * matching a row that `unique` called distinct is the kind of contradiction nobody thinks to test
 * for.
 *
 * ### It is almost nothing, and that is the point
 *
 * This used to be a three-rung ladder that decided *how* to compare based on what it had been
 * handed — ask an {@see Equality}, else compare objects with `==`, else `===`. The `==` rung was
 * the problem: it reads the private layout of whatever class a field happened to return, which is
 * what made `BigDecimal` call `12.50` and `12.5` different numbers.
 *
 * Every {@see \Meraki\Schema\Field\Definition::parse()} now hands back a value that answers for
 * itself, so the question "what kind of thing is this" no longer has to be asked, and the rung
 * that guessed is gone.
 *
 * What is left is the one thing that is not a parsed value: the **raw input**, which a result
 * keeps when `parse()` could not read it so a form can echo back what somebody actually typed.
 */
final class Values
{
	/**
	 * Whether two already-resolved values are the same value.
	 */
	public static function same(mixed $a, mixed $b): bool
	{
		if ($a instanceof Equality) {
			return $b instanceof Equality && $a->equals($b);
		}

		// `null`, and input no field could read. `===` is deep on arrays, so unreadable input that
		// happened to be a list still compares sensibly, and two values no field could make sense
		// of are the same only if they are identical — which is the most that can honestly be said.
		return $a === $b;
	}
}
