<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Number;

use Meraki\Schema\Exception\IncomparableValues;
use Brick\Math\Exception\MathException;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Comparison\Order;
use Meraki\Schema\Comparison\Comparable;
use Meraki\Schema\Field\ParsedValue;
use Brick\Math\BigDecimal;

/**
 * One number, as this library compares it.
 *
 * The only wrapper here that exists because the thing it wraps is *wrong* rather than merely
 * unpromising: `BigDecimal::of('12.50') == BigDecimal::of('12.5')` is false, because a `BigDecimal`
 * keeps the scale it was given. Two rows of a collection holding the same quantity written
 * differently counted as distinct, and a rule asking `equals(18)` of a field holding `18.0` did not
 * match. {@see self::equals()} compares numerically, which is what a number means.
 *
 * The `BigDecimal` is public and unchanged. Nothing is hidden by this — arithmetic, formatting and
 * scale are all still Brick's, and anything downstream that wants them reads {@see self::$number}.
 * What the wrapper adds is a definition of sameness the library owns.
 */
final readonly class Value implements ParsedValue, Comparable
{
	/** The number itself, at whatever scale it was written with. */
	public BigDecimal $number;

	/**
	 * Takes what the field takes, so there is one answer to "what is a number here" rather
	 * than a field that reads input and a value that trusts whatever it is handed.
	 *
	 * @throws MalformedValue if this is not a number
	 */
	public function __construct(float|int|string $number)
	{
		try {
			$this->number = BigDecimal::of($number);
		} catch (MathException) {
			throw MalformedValue::of(self::class, sprintf('"%s" is not a number', $number));
		}
	}

	/**
	 * Numerically, so `12.50` and `12.5` are one number. Trailing zeros are how a value was
	 * written, not what it is worth.
	 */
	public function equals(Equality $other): bool
	{
		return $other instanceof self && $this->number->isEqualTo($other->number);
	}

	/**
	 * @throws IncomparableValues if the other value is not a number
	 */
	public function compareTo(Comparable $other): Order
	{
		if (!$other instanceof self) {
			throw IncomparableValues::numbers();
		}

		return Order::of($this->number->compareTo($other->number));
	}

	/**
	 * The number as written, unpadded.
	 *
	 * Deliberately not padded to the field's scale. A field's scale is the field's, and this object
	 * does not know it — {@see \Meraki\Schema\Field\Number::$scale} is what a consumer wanting
	 * `123.00` reads, alongside this.
	 */
	public function __toString(): string
	{
		return (string) $this->number;
	}
}
