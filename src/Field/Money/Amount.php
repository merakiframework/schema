<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Money;

use Meraki\Schema\Comparison\Comparable;
use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Comparison\Order;
use Meraki\Schema\Exception\IncomparableValues;
use Brick\Math\BigDecimal;
use Stringable;

/**
 * Money's amount on its own: what a rule about the `amount` part compares.
 *
 * A part scope resolves to whatever the input holds in that part, and a bare `BigDecimal` is not
 * something a rule can compare: equality asked it as an object that is not this library's and got
 * `false`, and the ordered verbs need a {@see Comparable}. So
 * `when(PartScope::of('price', Part::Amount))->isAtLeast(10)` was accepted where it was written and
 * never held. {@see Input} hands over one of these instead, and reads the rule's expectation into
 * one too, so the rule compares two amounts.
 *
 * ### Without its currency
 *
 * Deliberately. A rule about the amount part asks about the number, whatever it is a number of —
 * naming the part is what says so. A question about both halves is a question about the whole
 * {@see Value}, which refuses to rank two currencies.
 *
 * ### Not `Number\Value`
 *
 * Which compares the same way, and belongs to another field. Sibling field types share no code, so
 * each can move into a package of its own without taking another with it — invariant 12 in
 * docs/DEVELOPER.md.
 */
final readonly class Amount implements Comparable, Stringable
{
	public function __construct(
		/** At whatever scale it was written with. */
		public BigDecimal $amount,
	) {
	}

	/**
	 * Numerically, so `12.50` and `12.5` are one amount. Trailing zeros are how it was written.
	 */
	public function equals(Equality $other): bool
	{
		return $other instanceof self && $this->amount->isEqualTo($other->amount);
	}

	/**
	 * @throws IncomparableValues if the other value is not an amount
	 */
	public function compareTo(Comparable $other): Order
	{
		if (!$other instanceof self) {
			throw IncomparableValues::becauseTheyAreNotAlike('An amount', 'another amount');
		}

		return Order::of($this->amount->compareTo($other->amount));
	}

	/**
	 * The number as written, so a rule matching text against the part has text to match.
	 *
	 * Unlike {@see Value}, which has none: a figure without its currency is not money, and
	 * printing one is not rendering money for a locale.
	 */
	public function __toString(): string
	{
		return (string) $this->amount;
	}
}
