<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\CreditCard;

use Meraki\Schema\Field;

/**
 * What a card field checks, by the code a failure is reported under.
 *
 * @see Field\Check for why a code is an enum rather than a string
 */
enum Check: string implements Field\Check
{
	case NumberRequired = 'numberRequired';
	case ExpiryRequired = 'expiryRequired';
	case NameRequired = 'nameRequired';
	case NumberFormat = 'numberFormat';
	case NumberChecksum = 'numberChecksum';
	case ExpiryInFuture = 'expiryInFuture';
	case ExpiryWithinReach = 'expiryWithinReach';
	case SecurityCodeFormat = 'securityCodeFormat';

	public function part(): Part
	{
		return match ($this) {
			self::NumberRequired, self::NumberFormat, self::NumberChecksum => Part::Number,
			self::ExpiryRequired, self::ExpiryInFuture, self::ExpiryWithinReach => Part::Expiry,
			self::NameRequired => Part::Name,
			self::SecurityCodeFormat => Part::SecurityCode,
		};
	}
}
