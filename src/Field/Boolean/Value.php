<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Boolean;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field\ParsedValue;

/**
 * One true/false answer, as this library compares it.
 *
 * There is nothing subtle here, and that is the point of the class existing anyway: a uniform
 * interface is worth more than the one exemption this would have earned.
 *
 * It also removes a trap. `parse()` used to return the bare `bool`, so a legitimate `false` had to be
 * kept distinct from the `null` that means unreadable, and the code said so in a comment. An object
 * is never falsy, so the distinction now holds by construction.
 *
 * A wrapper around something `===` already compared correctly, which is the trade made for a
 * uniform interface: every {@see \Meraki\Schema\Field\Definition::parse()} hands back one of these,
 * so nothing downstream ever branches on whether a value happens to be an object. The plain
 * bool is right there on {@see self::$answer}.
 */
final readonly class Value implements ParsedValue
{
	public function __construct(public bool $answer)
	{
	}

	public function equals(Equality $other): bool
	{
		return $other instanceof self && $this->answer === $other->answer;
	}
}
