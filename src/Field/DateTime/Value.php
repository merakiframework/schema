<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\DateTime;

use Brick\DateTime\DateTimeException;
use Meraki\Schema\Field\MalformedValue;
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
	/** The instant itself, which is what every comparison and constraint reads. */
	public LocalDateTime $dateTime;

	/**
	 * Takes either form, and both are needed.
	 *
	 * A **string** is what a request submits, and checking it against the grammar is this
	 * value's own business — no configuration makes `"25:00"` acceptable.
	 *
	 * A **LocalDateTime** is how {@see \Meraki\Schema\Field\DateTime} hands back a value it has
	 * applied its own precision policy to. That policy *is* configuration — a field may
	 * truncate seconds or refuse them — so it cannot live here, and re-serialising just to
	 * parse again would be a round trip for its own sake.
	 *
	 * @throws MalformedValue if a string is not a date and time, as YYYY-MM-DDTHH:MM
	 */
	public function __construct(string|LocalDateTime $dateTime)
	{
		if ($dateTime instanceof LocalDateTime) {
			$this->dateTime = $dateTime;

			return;
		}

		try {
			$this->dateTime = LocalDateTime::parse($dateTime);
		} catch (DateTimeException) {
			throw MalformedValue::of(self::class, sprintf('"%s" is not a date and time, as YYYY-MM-DDTHH:MM', $dateTime));
		}
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
