<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Time;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Comparison\Order;
use Meraki\Schema\Comparison\Comparable;
use Meraki\Schema\Field\ParsedValue;
use Brick\DateTime\LocalTime;
use InvalidArgumentException;

/**
 * One time of day, as this library compares it.
 *
 * As with {@see \Meraki\Schema\Field\Date\Value}: `==` happens to work on a `LocalTime` and is not
 * promised to.
 *
 * The wrapped value is public and unchanged; everything Brick offers is still reached through
 * {@see self::$time}. What this adds is a definition of sameness, and an order, that the
 * library owns.
 */
final readonly class Value implements ParsedValue, Comparable
{
	public function __construct(public LocalTime $time)
	{
	}

	public function equals(Equality $other): bool
	{
		return $other instanceof self && $this->time->isEqualTo($other->time);
	}

	/**
	 * @throws InvalidArgumentException if the other value is not a time of day
	 */
	public function compareTo(Comparable $other): Order
	{
		if (!$other instanceof self) {
			throw new InvalidArgumentException('A time of day can only be ordered against another time of day.');
		}

		return Order::of($this->time->compareTo($other->time));
	}

	public function __toString(): string
	{
		return (string) $this->time;
	}
}
