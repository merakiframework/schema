<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\CreditCard;

use Meraki\Schema\Comparison\Comparable;
use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Comparison\Order;
use Meraki\Schema\Exception\IncomparableValues;
use Brick\DateTime\LocalDate;
use Stringable;

/**
 * A card's expiry on its own: what a rule about the `expiry` part compares.
 *
 * The day the card stops being good, which for a month is its last day — see {@see Input}. A rule
 * is read the same way, so `isAtLeast('2027-01')` asks whether the card lasts to the end of January
 * 2027. A bare `LocalDate` used to stand here, which a rule could not compare at all: equality
 * asked it as an object that is not this library's, and the ordered verbs need a
 * {@see Comparable}.
 *
 * Its own type rather than `Date\Value`, which belongs to another field — invariant 12 in
 * docs/DEVELOPER.md.
 */
final readonly class Expiry implements Comparable, Stringable
{
	public function __construct(
		public LocalDate $date,
	) {
	}

	public function equals(Equality $other): bool
	{
		return $other instanceof self && $this->date->isEqualTo($other->date);
	}

	/**
	 * @throws IncomparableValues if the other value is not an expiry
	 */
	public function compareTo(Comparable $other): Order
	{
		if (!$other instanceof self) {
			throw IncomparableValues::becauseTheyAreNotAlike('A card expiry', 'another card expiry');
		}

		return Order::of($this->date->compareTo($other->date));
	}

	/**
	 * ISO 8601, `2026-09-30`.
	 */
	public function __toString(): string
	{
		return (string) $this->date;
	}
}
