<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Address;

use Meraki\Schema\Field;

/**
 * An address's six parts, in the order an address is written.
 *
 * Only the country is essential. A postcode, a subdivision list and the very question of which
 * parts are required are all selected *by* the country, so an address without one describes no
 * place. Every other part is something a country may or may not ask for, and a field may or may
 * not demand — see {@see Precision}.
 */
enum Part: string implements Field\Part
{
	case Street = 'street';
	case DependentLocality = 'dependent_locality';
	case Locality = 'locality';
	case Subdivision = 'subdivision';
	case PostalCode = 'postal_code';
	case Country = 'country';

	public function isEssential(): bool
	{
		return $this === self::Country;
	}

	/**
	 * The street is up to three lines, and nothing here joins them into delimited text — the
	 * separator would have to be CRLF or LF, and HTML and JSON disagree.
	 */
	public function isList(): bool
	{
		return $this === self::Street;
	}
}
