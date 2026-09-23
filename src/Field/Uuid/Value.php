<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Uuid;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Field\ParsedValue;

/**
 * One UUID, as this library compares it.
 *
 * **Lower-cased on the way in**, because RFC 9562 says both which spellings are one identifier and
 * which of them is canonical: the hex digits are case-insensitive on input and lowercase on
 * output. So the folding happens once, here, rather than at every comparison.
 *
 * It used to be a `strcasecmp()` inside `equals()`, which got the comparison right and left
 * everything else wrong: two equal UUIDs still read back as different strings, so a message
 * interpolating one or a document serialising one showed whichever case was typed.
 *
 * A wrapper around something `===` already compared correctly, which is the trade made for a
 * uniform interface: every {@see \Meraki\Schema\Field\Definition::parse()} hands back one of these,
 * so nothing downstream ever branches on whether a value happens to be an object. The plain
 * string is right there on {@see self::$uuid}.
 */
final readonly class Value implements ParsedValue
{
	/** RFC 9562 §4, plus the nil and max UUIDs, which no version digit covers. */
	private const PATTERN = '/^(?:[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}|00000000-0000-0000-0000-000000000000|ffffffff-ffff-ffff-ffff-ffffffffffff)$/i';

	/** The canonical lowercase form, whatever case it arrived in. */
	public string $uuid;

	/**
	 * @throws MalformedValue if this is not a UUID
	 */
	public function __construct(string $uuid)
	{
		if (preg_match(self::PATTERN, $uuid) !== 1) {
			throw MalformedValue::of(self::class, sprintf('"%s" is not a UUID', $uuid));
		}

		$this->uuid = strtolower($uuid);
	}

	/**
	 * Exact, because both sides are already canonical. The case-folding that used to live here
	 * happens once at construction instead of on every comparison.
	 */
	public function equals(Equality $other): bool
	{
		return $other instanceof self && $this->uuid === $other->uuid;
	}

	public function __toString(): string
	{
		return $this->uuid;
	}
}
