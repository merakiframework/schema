<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\DateTime;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Comparison\Order;
use Meraki\Schema\Comparison\Comparable;
use Meraki\Schema\Field\ParsedValue;
use Brick\DateTime\LocalDateTime;
use InvalidArgumentException;

/**
 * One date and time, as this library compares it.
 *
 * As with {@see \Meraki\Schema\Field\Date\Value}: `==` happens to work on a `LocalDateTime` and is
 * not promised to. The precision a field accepts is the *field's* business — see
 * {@see \Meraki\Schema\Field\DateTime::$precision} — so two instants that differ only below the
 * accepted precision are still two instants here, and the field is what refuses the finer one.
 *
 * The wrapped value is public and unchanged; everything Brick offers is still reached through
 * {@see self::$dateTime}. What this adds is a definition of sameness, and an order, that the
 * library owns.
 */
final readonly class Value implements ParsedValue, Comparable
{
	public function __construct(public LocalDateTime $dateTime)
	{
	}

	public function equals(Equality $other): bool
	{
		return $other instanceof self && $this->dateTime->isEqualTo($other->dateTime);
	}

	/**
	 * @throws InvalidArgumentException if the other value is not a date and time
	 */
	public function compareTo(Comparable $other): Order
	{
		if (!$other instanceof self) {
			throw new InvalidArgumentException('A date and time can only be ordered against another date and time.');
		}

		return Order::of($this->dateTime->compareTo($other->dateTime));
	}

	public function __toString(): string
	{
		return (string) $this->dateTime;
	}
}
