<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Date;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Comparison\Order;
use Meraki\Schema\Comparison\Comparable;
use Meraki\Schema\Field\ParsedValue;
use Brick\DateTime\LocalDate;
use InvalidArgumentException;

/**
 * One calendar date, as this library compares it.
 *
 * A `LocalDate` compares correctly with `==` today, because it stores a year, a month and a day and
 * nothing else. That is an implementation detail of Brick's, not a promise to this library — a
 * memoised formatted string added to it in some later release would make two equal dates compare
 * unequal, silently, with nothing here having changed. Asking the date is the version of this that
 * cannot rot.
 *
 * The wrapped value is public and unchanged; everything Brick offers is still reached through
 * {@see self::$date}. What this adds is a definition of sameness, and an order, that the
 * library owns.
 */
final readonly class Value implements ParsedValue, Comparable
{
	public function __construct(public LocalDate $date)
	{
	}

	public function equals(Equality $other): bool
	{
		return $other instanceof self && $this->date->isEqualTo($other->date);
	}

	/**
	 * @throws InvalidArgumentException if the other value is not a calendar date
	 */
	public function compareTo(Comparable $other): Order
	{
		if (!$other instanceof self) {
			throw new InvalidArgumentException('A calendar date can only be ordered against another calendar date.');
		}

		return Order::of($this->date->compareTo($other->date));
	}

	public function __toString(): string
	{
		return (string) $this->date;
	}
}
