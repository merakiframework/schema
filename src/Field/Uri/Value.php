<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Uri;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Field\ParsedValue;
use Uri\InvalidUriException;
use Uri\Rfc3986\Uri as Rfc3986Uri;

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
	/**
	 * The scheme, lower-cased, or null for a relative reference.
	 *
	 * Kept because the parse has already happened and throwing it away meant parsing again on
	 * every `allowedSchemes` check — of a string that had, by then, already been proved to be
	 * a URI.
	 */
	public ?string $scheme;

	/**
	 * Parsed with PHP's own RFC 3986 implementation rather than a pattern of our own: the
	 * grammar is a matter of public record, and the pattern this replaced had every group
	 * optional, so it accepted any string at all.
	 *
	 * @throws MalformedValue if this is not a URI
	 */
	public function __construct(public string $uri)
	{
		if ($uri === '') {
			throw MalformedValue::of(self::class, 'an empty string is not a URI');
		}

		try {
			$parsed = new Rfc3986Uri($uri);
		} catch (InvalidUriException $notAUri) {
			throw MalformedValue::of(self::class, sprintf('"%s" is not a URI', $uri));
		}

		$scheme = $parsed->getScheme();
		$this->scheme = $scheme === null ? null : strtolower($scheme);
	}

	public function equals(Equality $other): bool
	{
		return $other instanceof self && $this->uri === $other->uri;
	}

	public function __toString(): string
	{
		return $this->uri;
	}
}
