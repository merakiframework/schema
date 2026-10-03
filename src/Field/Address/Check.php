<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Address;

use Meraki\Schema\Field;

/**
 * What an address field checks, by the code a failure is reported under.
 *
 * @see Field\Check for why a code is an enum rather than a string
 */
enum Check: string implements Field\Check
{
	case CountryRequired = 'countryRequired';
	case AllowedCountries = 'allowedCountries';
	case StreetRequired = 'streetRequired';
	case StreetLineLimit = 'streetLineLimit';
	case StreetVisitable = 'streetVisitable';
	case LocalityRequired = 'localityRequired';
	case LocalityUsed = 'localityUsed';
	case DependentLocalityUsed = 'dependentLocalityUsed';
	case SubdivisionRequired = 'subdivisionRequired';
	case SubdivisionUsed = 'subdivisionUsed';
	case KnownSubdivision = 'knownSubdivision';
	case PostalCodeRequired = 'postalCodeRequired';
	case PostalCodeUsed = 'postalCodeUsed';
	case PostalCodeFormat = 'postalCodeFormat';

	public function part(): Part
	{
		return match ($this) {
			self::CountryRequired, self::AllowedCountries => Part::Country,
			self::StreetRequired, self::StreetLineLimit, self::StreetVisitable => Part::Street,
			self::LocalityRequired, self::LocalityUsed => Part::Locality,
			self::DependentLocalityUsed => Part::DependentLocality,
			self::SubdivisionRequired, self::SubdivisionUsed, self::KnownSubdivision => Part::Subdivision,
			self::PostalCodeRequired, self::PostalCodeUsed, self::PostalCodeFormat => Part::PostalCode,
		};
	}
}
