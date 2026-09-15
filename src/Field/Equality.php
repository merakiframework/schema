<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

/**
 * The one place this library answers "are these two values the same thing".
 *
 * Two callers need that answer and must not disagree about it: {@see Collection}, deciding whether
 * two rows repeat, and {@see \Meraki\Schema\Rule\Condition\Comparison}, deciding whether a field
 * holds what a rule is asking about. Written twice they would drift, and a rule matching a row that
 * `unique` called distinct is the kind of contradiction nobody thinks to test for.
 *
 * ### It is almost nothing, and that is the point
 *
 * This used to be a three-rung ladder that decided *how* to compare based on what it had been
 * handed — ask a {@see ParsedValue}, else compare objects with `==`, else `===`. The `==` rung was
 * the problem: it compares two objects property by property, so it was reading the private layout
 * of whatever class a field happened to return. That is what made `BigDecimal` call `12.50` and
 * `12.5` different numbers.
 *
 * Every {@see Definition::parse()} now hands back a value object this library defines, so the
 * question "what kind of thing is this" no longer has to be asked. The value is asked instead, and
 * the rung that guessed is gone.
 *
 * What is left is one edge: the **raw input**, which a result keeps when `parse()` could not read
 * it, so a form can echo back what somebody actually typed. Those compare identically, which is the
 * most that can honestly be said about values no field could make sense of.
 *
 * A collection is not an exception either — {@see Collection\Value} compares its own rows, and each
 * row's leaves come back through here.
 */
final class Equality
{
	/**
	 * Whether two already-resolved values are the same value.
	 */
	public static function same(mixed $a, mixed $b): bool
	{
		if ($a instanceof ParsedValue) {
			return $b instanceof ParsedValue && $a->equals($b);
		}

		// Everything that is not a parsed value: `null`, and the raw input a result keeps when
		// `parse()` could not read it. `===` is deep on arrays, so unreadable input that happened
		// to be a list still compares sensibly, and two values no field could make sense of are
		// the same only if they are identical — which is the most that can honestly be said.
		return $a === $b;
	}
}
