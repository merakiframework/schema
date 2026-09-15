<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Text;

use Meraki\Schema\Field\ParsedValue;

/**
 * One run of text, as this library compares it.
 *
 * Text has no canonical form: `Straße` and `STRASSE` are different text, and so are `a ` and `a`.
 * Case-folding or trimming here would be the repair this library does not do — see docs/CODING-STYLE.md.
 * So equality is exact, which is what `===` on the string already meant.
 *
 * A wrapper around something `===` already compared correctly, which is the trade made for a
 * uniform interface: every {@see \Meraki\Schema\Field\Definition::parse()} hands back one of these,
 * so nothing downstream ever branches on whether a value happens to be an object. The plain
 * string is right there on {@see self::$text}.
 */
final readonly class Value implements ParsedValue
{
	public function __construct(public string $text)
	{
	}

	public function equals(ParsedValue $other): bool
	{
		return $other instanceof self && $this->text === $other->text;
	}

	public function __toString(): string
	{
		return $this->text;
	}
}
