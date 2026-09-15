<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Number;

use Meraki\Schema\Field\Comparable;
use Meraki\Schema\Field\ParsedValue;
use Brick\Math\BigDecimal;
use InvalidArgumentException;

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
final readonly class Value implements Comparable
{
	public function __construct(public BigDecimal $number)
	{
	}

	/**
	 * Numerically, so `12.50` and `12.5` are one number. Trailing zeros are how a value was
	 * written, not what it is worth.
	 */
	public function equals(ParsedValue $other): bool
	{
		return $other instanceof self && $this->number->isEqualTo($other->number);
	}

	/**
	 * @throws InvalidArgumentException if the other value is not a number
	 */
	public function compareTo(Comparable $other): int
	{
		if (!$other instanceof self) {
			throw new InvalidArgumentException('A number can only be ordered against another number.');
		}

		return $this->number->compareTo($other->number);
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
