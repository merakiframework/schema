<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Address;

use Meraki\Schema\Field;

/**
 * What an address field checks, by the code a failure is reported under.
 *
 * The first twelve decide whether what arrived is an address at all — whether it names a country,
 * and whether each part is one that country's published format has a place for and can read — and
 * are reported by {@see Input} before anything else is asked. The last six decide whether this
 * field accepts it: which countries, how much of the address it demands, and whether it must be
 * somewhere a person can go.
 *
 * @see Field\Check for why a code is an enum rather than a string
 */
enum Check: string implements Field\Check
{
	case CountryRequired = 'countryRequired';
	case KnownCountry = 'knownCountry';
	case StreetFormat = 'streetFormat';
	case StreetLineLimit = 'streetLineLimit';
	case DependentLocalityFormat = 'dependentLocalityFormat';
	case DependentLocalityUsed = 'dependentLocalityUsed';
	case LocalityFormat = 'localityFormat';
	case LocalityUsed = 'localityUsed';
	case KnownSubdivision = 'knownSubdivision';
	case SubdivisionUsed = 'subdivisionUsed';
	case PostalCodeFormat = 'postalCodeFormat';
	case PostalCodeUsed = 'postalCodeUsed';
	case AllowedCountries = 'allowedCountries';
	case StreetVisitable = 'streetVisitable';
	case StreetRequired = 'streetRequired';
	case LocalityRequired = 'localityRequired';
	case SubdivisionRequired = 'subdivisionRequired';
	case PostalCodeRequired = 'postalCodeRequired';

	public function part(): Part
	{
		return match ($this) {
			self::CountryRequired, self::KnownCountry, self::AllowedCountries => Part::Country,
			self::StreetFormat, self::StreetLineLimit, self::StreetVisitable, self::StreetRequired => Part::Street,
			self::DependentLocalityFormat, self::DependentLocalityUsed => Part::DependentLocality,
			self::LocalityFormat, self::LocalityUsed, self::LocalityRequired => Part::Locality,
			self::KnownSubdivision, self::SubdivisionUsed, self::SubdivisionRequired => Part::Subdivision,
			self::PostalCodeFormat, self::PostalCodeUsed, self::PostalCodeRequired => Part::PostalCode,
		};
	}
}
