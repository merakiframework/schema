<?php
declare(strict_types=1);

namespace Meraki\Schema\Comparison;

use InvalidArgumentException;

/**
 * A value with an order, not merely an identity.
 *
 * Separate from {@see Equality} because most values do not have one. Two addresses can be the same
 * address; neither is *before* the other. A phone number has no order at all. Numbers, dates,
 * times, durations and amounts of money do — and they are what a rule wants to ask "is this at
 * least 18" about.
 *
 * ### The second composable primitive
 *
 * This is the whole of what the comparison matchers need. Every one of them is this method and a
 * question put to the {@see Order} it returns, so they are written once against the interface
 * rather than once per field type:
 *
 * | Matcher | Derivation |
 * | --- | --- |
 * | `isGreaterThan` | `compareTo($x)->isGreater()` |
 * | `isAtLeast` | `compareTo($x)->isAtLeast()` |
 * | `isLessThan` | `compareTo($x)->isLess()` |
 * | `isAtMost` | `compareTo($x)->isAtMost()` |
 * | `isBetween` | both bounds, in one condition |
 *
 * All five exist — see {@see \Meraki\Schema\Rule\Condition\Ordered}, which is where they are
 * written once against this interface. This is the part of them that belongs to the value, and
 * implementing it on a new type is the whole of what that type needs to become orderable.
 *
 * ### Ordering is within a kind
 *
 * Comparing a date to a duration is not a question with an answer, so it raises rather than
 * inventing one. The same holds *within* a type where the type says so: two amounts of money in
 * different currencies are not ordered, and {@see \Meraki\Schema\Field\Money\Value} raises rather
 * than pretending 5 USD and 5 AUD can be ranked.
 *
 * Raising is already the contract for "these cannot be compared", which is why money belongs here
 * at all — an `isAtLeast` on a money field is an obviously wanted rule, and excluding the type
 * outright to avoid one raising case would have cost more than it saved.
 */
interface Comparable extends Equality
{
	/**
	 * Where this value sits relative to `$other`.
	 *
	 * Consistent with {@see Equality::equals()} by construction: {@see Order::Equal} and a `true`
	 * from `equals()` must always agree, because a value that sorts equal to another and is not
	 * equal to it has no coherent reading.
	 *
	 * @throws InvalidArgumentException if the two are not comparable
	 */
	public function compareTo(self $other): Order;
}
