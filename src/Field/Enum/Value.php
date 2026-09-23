<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Enum;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field\ParsedValue;

/**
 * One chosen case, as this library compares it.
 *
 * Exactly, because a case is a string and nothing else — see {@see \Meraki\Schema\Field\Enum}
 * for why the other scalars are refused rather than merely unused.
 *
 * A wrapper around something `===` already compared correctly, which is the trade made for a
 * uniform interface: every {@see \Meraki\Schema\Field\Definition::parse()} hands back one of these,
 * so nothing downstream ever branches on whether a value happens to be an object. The plain
 * string is right there on {@see self::$case}, and `(string) $value` is the same string.
 */
final readonly class Value implements ParsedValue
{
	/**
	 * The one value in this library whose constructor enforces nothing beyond its type, and
	 * the exception is worth naming.
	 *
	 * Everywhere else, "is this the kind of thing I hold" is a fact about the value —
	 * `"25:00"` is not a time under any configuration. Here it is a fact about the *field*:
	 * membership of a case list the author supplied, which a value has no way to see. So
	 * {@see \Meraki\Schema\Field\Enum::parse()} keeps that check, and it is the shape rather
	 * than a constraint — an enum renderer reads `$cases` to draw the options anyway, so a
	 * bound carrying them would say nothing new.
	 */
	public function __construct(public string $case)
	{
	}

	public function equals(Equality $other): bool
	{
		return $other instanceof self && $this->case === $other->case;
	}

	/**
	 * The chosen case.
	 *
	 * The same string {@see self::$case} holds, and both are kept for the same reason
	 * {@see \Meraki\Schema\Field\Text\Value} keeps `$text` beside its own: the property is
	 * what a consumer compares, and this is what a template interpolates.
	 *
	 * There is no rendering decision left to make. While a case could be any scalar this had to
	 * say what a `false` looked like, because PHP casts it to the empty string and a template
	 * would have shown nothing at all.
	 */
	public function __toString(): string
	{
		return $this->case;
	}
}
