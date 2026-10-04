<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\CreditCard;

use Meraki\Schema\Field;

/**
 * What a card field checks, by the code a failure is reported under.
 *
 * The first seven decide whether what arrived is a card at all, and are reported by {@see Input}
 * before anything else is asked. The rest are the field's constraints. Two ask what day it is, so
 * a card's expiry is judged per request, never where a default is written; two ask for a part a
 * card can be without, which a field may demand and another decline.
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
	case NameRequired = 'nameRequired';
	case SecurityCodeRequired = 'securityCodeRequired';

	public function part(): Part
	{
		return match ($this) {
			self::NumberRequired, self::NumberFormat, self::NumberChecksum => Part::Number,
			self::ExpiryRequired, self::ExpiryFormat, self::ExpiryInFuture, self::ExpiryWithinReach => Part::Expiry,
			self::NameFormat, self::NameRequired => Part::Name,
			self::SecurityCodeFormat, self::SecurityCodeRequired => Part::SecurityCode,
		};
	}
}
