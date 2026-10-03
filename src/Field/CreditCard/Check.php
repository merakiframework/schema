<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\CreditCard;

use Meraki\Schema\Field;

/**
 * What a card field checks, by the code a failure is reported under.
 *
 * All but the last two decide whether what arrived is a card at all, and are reported by
 * {@see Input} before anything else is asked. The last two ask what day it is, so they are the
 * field's constraints: a card's expiry is judged per request, never where a default is written.
 *
 * There is no `nameRequired`. A cardholder's name is optional, like the security code — plenty of
 * flows never ask for either.
 *
 * @see Field\Check for why a code is an enum rather than a string
 */
enum Check: string implements Field\Check
{
	case NumberRequired = 'numberRequired';
	case ExpiryRequired = 'expiryRequired';
	case NumberFormat = 'numberFormat';
	case NumberChecksum = 'numberChecksum';
	case ExpiryFormat = 'expiryFormat';
	case NameFormat = 'nameFormat';
	case SecurityCodeFormat = 'securityCodeFormat';
	case ExpiryInFuture = 'expiryInFuture';
	case ExpiryWithinReach = 'expiryWithinReach';

	public function part(): Part
	{
		return match ($this) {
			self::NumberRequired, self::NumberFormat, self::NumberChecksum => Part::Number,
			self::ExpiryRequired, self::ExpiryFormat, self::ExpiryInFuture, self::ExpiryWithinReach => Part::Expiry,
			self::NameFormat => Part::Name,
			self::SecurityCodeFormat => Part::SecurityCode,
		};
	}
}
