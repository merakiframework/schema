<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Enum;

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
	public function __construct(public string|int|float|bool $case)
	{
	}

	public function equals(ParsedValue $other): bool
	{
		return $other instanceof self && $this->case === $other->case;
	}
}
