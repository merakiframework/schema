<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Duration;

use Meraki\Schema\Exception\IncomparableValues;
use Brick\DateTime\DateTimeException;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Comparison\Order;
use Meraki\Schema\Comparison\Comparable;
use Meraki\Schema\Field\ParsedValue;
use Brick\DateTime\Duration as BrickDuration;

/**
 * One length of time, as this library compares it.
 *
 * `Duration::parse('PT1H') == Duration::parse('PT60M')` is true, which is the right answer — but it
 * is true because Brick normalises both to seconds internally, not because anything says it must
 * be. Asking the duration makes the right answer the guaranteed one.
 *
 * This wrapper also has a job the others do not: docs/ROADMAP.md plans to move this field onto
 * PHP 8.6's native duration type. Behind a value object that swap is internal. Returning Brick's
 * class from `parse()` would have made it a breaking change to every consumer reading `$value`.
 *
 * The wrapped value is public and unchanged; everything Brick offers is still reached through
 * {@see self::$duration}. What this adds is a definition of sameness, and an order, that the
 * library owns.
 */
final readonly class Value implements ParsedValue, Comparable
{
	/** The length itself, normalised to seconds and nanoseconds by Brick. */
	public BrickDuration $duration;

	/**
	 * @param string $duration an ISO 8601 duration, which is what the field accepts
	 * @throws MalformedValue if this is not one
	 */
	public function __construct(string $duration)
	{
		try {
			$this->duration = BrickDuration::parse($duration);
		} catch (DateTimeException) {
			throw MalformedValue::of(self::class, sprintf('"%s" is not a duration, as PT1H30M', $duration));
		}
	}

	public function equals(Equality $other): bool
	{
		return $other instanceof self && $this->duration->isEqualTo($other->duration);
	}

	/**
	 * @throws IncomparableValues if the other value is not a length of time
	 */
	public function compareTo(Comparable $other): Order
	{
		if (!$other instanceof self) {
			throw IncomparableValues::lengthsOfTime();
		}

		return Order::of($this->duration->compareTo($other->duration));
	}

	public function __toString(): string
	{
		return (string) $this->duration;
	}
}
