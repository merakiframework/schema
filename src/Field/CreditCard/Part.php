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
	 * The security code is the one part a card may be without: plenty of flows never ask for one.
	 */
	public function isEssential(): bool
	{
		return $this !== self::SecurityCode;
	}

	public function isList(): bool
	{
		return false;
	}
}
