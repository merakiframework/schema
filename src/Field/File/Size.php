<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\File;

use Meraki\Schema\Comparison\Comparable;
use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Comparison\Order;
use Meraki\Schema\Exception\IncomparableValues;
use Meraki\Schema\Field\MalformedValue;
use Stringable;

/**
 * A file's reported size on its own: what a rule about the `size` part compares.
 *
 * A bare `int` used to stand here. Equality on one happened to work; the ordered verbs need a
 * {@see Comparable}, so `isAtMost(1048576)` against the part was accepted where it was written and
 * never held. {@see Input} reads the rule's expectation into one of these too, so `'1048576'` —
 * the size as a form posts it — compares as the number it is.
 *
 * Its own type rather than `Number\Value`, which belongs to another field — invariant 12 in
 * docs/DEVELOPER.md.
 */
final readonly class Size implements Comparable, Stringable
{
	/**
	 * @param int<0, max> $bytes
	 * @throws MalformedValue if it is negative
	 */
	public function __construct(
		public int $bytes,
	) {
		if ($bytes < 0) {
			throw MalformedValue::of(self::class, "{$bytes} bytes is not a size");
		}
	}

	public function equals(Equality $other): bool
	{
		return $other instanceof self && $this->bytes === $other->bytes;
	}

	/**
	 * @throws IncomparableValues if the other value is not a size
	 */
	public function compareTo(Comparable $other): Order
	{
		if (!$other instanceof self) {
			throw IncomparableValues::becauseTheyAreNotAlike('A file size', 'another file size');
		}

		return Order::of($this->bytes <=> $other->bytes);
	}

	/**
	 * The number of bytes, so a rule matching text against the part has text to match.
	 */
	public function __toString(): string
	{
		return (string) $this->bytes;
	}
}
