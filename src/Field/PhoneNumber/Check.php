<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\PhoneNumber;

use Meraki\Schema\Field;

/**
 * What a phone number field checks, by the code a failure is reported under.
 *
 * The first five decide whether what arrived is a phone number at all, and are reported by
 * {@see Input} before anything else is asked. The last two decide whether this field accepts it.
 *
 * @see Field\Check for why a code is an enum rather than a string
 */
enum Check: string implements Field\Check
{
	case NumberRequired = 'numberRequired';
	case CountryRequired = 'countryRequired';
	case NumberFormat = 'numberFormat';
	case KnownCountry = 'knownCountry';
	case NumberInCountry = 'numberInCountry';
	case AllowedCountries = 'allowedCountries';
	case NumberType = 'numberType';

	public function part(): Part
	{
		return match ($this) {
			self::NumberRequired, self::NumberFormat, self::NumberInCountry, self::NumberType => Part::Number,
			self::CountryRequired, self::KnownCountry, self::AllowedCountries => Part::Country,
		};
	}
}
