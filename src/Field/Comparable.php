<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use InvalidArgumentException;

/**
 * A value with an order, not merely an identity.
 *
 * Separate from {@see ParsedValue} because most values do not have one. Two addresses can be the same
 * address; neither is *before* the other. An amount of money is ordered only within a currency, and
 * a phone number not at all. Numbers, dates, times and durations are — and they are the fields a
 * rule wants to ask "is this at least 18" about.
 *
 * ### The second composable primitive
 *
 * This is the whole of what the comparison matchers need. Every one of them is this method and a
 * comparison against zero, so they are written once against the interface rather than once per
 * field type:
 *
 * | Matcher | Derivation |
 * | --- | --- |
 * | `isGreaterThan` | `compareTo($x) > 0` |
 * | `isAtLeast` | `compareTo($x) >= 0` |
 * | `isLessThan` | `compareTo($x) < 0` |
 * | `isAtMost` | `compareTo($x) <= 0` |
 * | `isBetween` | both bounds, in one condition |
 *
 * The matchers themselves are still to come — see docs/ROADMAP.md. This is the part of them that
 * belongs to the value, and it is here now because it is the same decision as {@see ParsedValue}: a
 * field that parses to something ordered should say so where the compiler can see it, not leave
 * each matcher to work it out per type.
 *
 * ### Ordering is within a kind
 *
 * Comparing a date to a duration is not a question with an answer, so it raises rather than
 * inventing one. A rule that manages to ask it is an authoring mistake, and
 * {@see \Meraki\Schema\Facade::addRule()} already refuses the ones it can see coming.
 */
interface Comparable extends ParsedValue
{
	/**
	 * Negative if this sorts before `$other`, zero if they are the same, positive if after.
	 *
	 * Consistent with {@see ParsedValue::equals()} by construction: a zero here and a true there must
	 * always agree, because a value that sorts equal to another and is not equal to it has no
	 * coherent reading.
	 *
	 * @throws InvalidArgumentException if the two are not the same kind of value
	 */
	public function compareTo(self $other): int;
}
