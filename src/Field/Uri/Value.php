<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Uri;

use Meraki\Schema\Field\ParsedValue;

/**
 * One URI, as this library compares it.
 *
 * Exact on the string as submitted. RFC 3986 normalisation — lower-casing the scheme and host,
 * resolving dot segments, dropping a default port — would make two spellings of one resource compare
 * equal, and it is genuinely wanted; it belongs with the richer `Uri` planned in docs/ROADMAP.md,
 * built on PHP 8.5's own `Uri\Rfc3986\Uri`. This wrapper is what makes adding it later a change to
 * one method rather than a change to what `parse()` returns.
 *
 * A wrapper around something `===` already compared correctly, which is the trade made for a
 * uniform interface: every {@see \Meraki\Schema\Field\Definition::parse()} hands back one of these,
 * so nothing downstream ever branches on whether a value happens to be an object. The plain
 * string is right there on {@see self::$uri}.
 */
final readonly class Value implements ParsedValue
{
	public function __construct(public string $uri)
	{
	}

	public function equals(ParsedValue $other): bool
	{
		return $other instanceof self && $this->uri === $other->uri;
	}

	public function __toString(): string
	{
		return $this->uri;
	}
}
