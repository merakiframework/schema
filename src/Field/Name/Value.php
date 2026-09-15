<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Name;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field\ParsedValue;

/**
 * One personal name, as this library compares it.
 *
 * Exact, and deliberately not clever. Two spellings of one person's name are a problem no library
 * should decide it has solved: `de la Cruz` and `De La Cruz` may be one person or two, and a schema
 * has no way to know which.
 *
 * A wrapper around something `===` already compared correctly, which is the trade made for a
 * uniform interface: every {@see \Meraki\Schema\Field\Definition::parse()} hands back one of these,
 * so nothing downstream ever branches on whether a value happens to be an object. The plain
 * string is right there on {@see self::$name}.
 */
final readonly class Value implements ParsedValue
{
	public function __construct(public string $name)
	{
	}

	public function equals(Equality $other): bool
	{
		return $other instanceof self && $this->name === $other->name;
	}

	public function __toString(): string
	{
		return $this->name;
	}
}
