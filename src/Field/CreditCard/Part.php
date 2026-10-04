<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\CreditCard;

use Meraki\Schema\Field;

/**
 * A payment card's parts, as a form collects them.
 */
enum Part: string implements Field\Part
{
	case Number = 'number';
	case Expiry = 'expiry';
	case Name = 'name';
	case SecurityCode = 'security_code';

	/**
	 * A number and an expiry, and nothing else. The name and the security code are optional:
	 * plenty of flows never ask for either — a stored card being re-authorised, a terminal
	 * reading the chip, a processor that does not want the name.
	 */
	public function isEssential(): bool
	{
		return $this === self::Number || $this === self::Expiry;
	}

	public function isList(): bool
	{
		return false;
	}
}
