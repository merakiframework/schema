<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Time;

use Meraki\Schema\Exception\IncomparableValues;
use Brick\DateTime\DateTimeException;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Comparison\Order;
use Meraki\Schema\Comparison\Comparable;
use Meraki\Schema\Field\ParsedValue;
use Brick\DateTime\LocalTime;

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
	/** The instant itself, which is what every comparison and constraint reads. */
	public LocalTime $time;

	/**
	 * Takes either form, and both are needed.
	 *
	 * A **string** is what a request submits, and checking it against the grammar is this
	 * value's own business — no configuration makes `"25:00"` acceptable.
	 *
	 * A **LocalTime** is how {@see \Meraki\Schema\Field\Time} hands back a value it has
	 * applied its own precision policy to. That policy *is* configuration — a field may
	 * truncate seconds or refuse them — so it cannot live here, and re-serialising just to
	 * parse again would be a round trip for its own sake.
	 *
	 * @throws MalformedValue if a string is not a time, as HH:MM or HH:MM:SS
	 */
	public function __construct(string|LocalTime $time)
	{
		if ($time instanceof LocalTime) {
			$this->time = $time;

			return;
		}

		try {
			$this->time = LocalTime::parse($time);
		} catch (DateTimeException) {
			throw MalformedValue::of(self::class, sprintf('"%s" is not a time, as HH:MM or HH:MM:SS', $time));
		}
	}

	public function equals(Equality $other): bool
	{
		return $other instanceof self && $this->time->isEqualTo($other->time);
	}

	/**
	 * @throws IncomparableValues if the other value is not a time of day
	 */
	public function compareTo(Comparable $other): Order
	{
		if (!$other instanceof self) {
			throw IncomparableValues::timesOfDay();
		}

		return Order::of($this->time->compareTo($other->time));
	}

	public function __toString(): string
	{
		return (string) $this->time;
	}
}
