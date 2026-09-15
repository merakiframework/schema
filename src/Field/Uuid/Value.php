<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Uuid;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field\ParsedValue;

/**
 * One UUID, as this library compares it.
 *
 * Case-insensitively, because RFC 9562 says so: a UUID's hex digits may be written in either case
 * and the two spellings are the same identifier. `===` on the string called them different, so two
 * collection rows holding one UUID in different cases counted as two.
 *
 * A wrapper around something `===` already compared correctly, which is the trade made for a
 * uniform interface: every {@see \Meraki\Schema\Field\Definition::parse()} hands back one of these,
 * so nothing downstream ever branches on whether a value happens to be an object. The plain
 * string is right there on {@see self::$uuid}.
 */
final readonly class Value implements ParsedValue
{
	public function __construct(public string $uuid)
	{
	}

	public function equals(Equality $other): bool
	{
		return $other instanceof self && strcasecmp($this->uuid, $other->uuid) === 0;
	}

	public function __toString(): string
	{
		return $this->uuid;
	}
}
