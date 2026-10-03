<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\PhoneNumber;

use Meraki\Schema\Field;

/**
 * What a phone number field checks, by the code a failure is reported under.
 *
 * @see Field\Check for why a code is an enum rather than a string
 */
enum Check: string implements Field\Check
{
	case NumberRequired = 'numberRequired';
	case CountryRequired = 'countryRequired';
	case AllowedCountries = 'allowedCountries';
	case NumberType = 'numberType';

	public function part(): Part
	{
		return match ($this) {
			self::NumberRequired, self::NumberType => Part::Number,
			self::CountryRequired, self::AllowedCountries => Part::Country,
		};
	}
}
