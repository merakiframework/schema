<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Enum;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field\ParsedValue;

/**
 * One chosen case, as this library compares it.
 *
 * Identically, including type: the cases a field declares are all of one type — {@see \Meraki\Schema\Field\Enum}
 * refuses a mixed list — so `1` and `'1'` never both appear as cases, and treating them as the same
 * would only ever hide a mistake.
 *
 * A wrapper around something `===` already compared correctly, which is the trade made for a
 * uniform interface: every {@see \Meraki\Schema\Field\Definition::parse()} hands back one of these,
 * so nothing downstream ever branches on whether a value happens to be an object. The plain
 * string|int|float|bool is right there on {@see self::$case}.
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
	public function __construct(public string|int|float|bool $case)
	{
	}

	public function equals(Equality $other): bool
	{
		return $other instanceof self && $this->case === $other->case;
	}

	/**
	 * The chosen case, as text.
	 *
	 * `{@see self::$case}` stays, and is not redundant: it holds the case at its *declared type*,
	 * so `$value->case === 3` is true for an enum of integers where `(string) $value === '3'`. The
	 * same arrangement {@see \Meraki\Schema\Field\Number\Value} has — a `BigDecimal` on the
	 * property, a rendering here. Reach for the property to compare, this to display.
	 *
	 * ### A boolean case is `true` or `false`, not `1` and nothing
	 *
	 * PHP casts `false` to the empty string, which a template renders as nothing at all — an enum
	 * whose chosen case simply vanished. Every other scalar casts sensibly, so this is the one
	 * place the rendering is spelled out rather than delegated.
	 *
	 * Worth saying that a two-case boolean enum is a {@see \Meraki\Schema\Field\Boolean} written
	 * the long way round, and `Boolean` has `mustBeAccepted()` and a matcher that suits it. This
	 * makes the unusual case render correctly; it is not encouragement.
	 */
	public function __toString(): string
	{
		if (is_bool($this->case)) {
			return $this->case ? 'true' : 'false';
		}

		return (string) $this->case;
	}
}
