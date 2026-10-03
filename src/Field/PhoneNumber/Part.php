<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\PhoneNumber;

use Meraki\Schema\Field;

/**
 * A telephone number's two parts: the number as typed, and the country to read it in.
 *
 * Both are essential. `0411 222 333` is a different number in a different country, and a country
 * on its own is not a phone number.
 */
enum Part: string implements Field\Part
{
	case Number = 'number';
	case Country = 'country';

	public function isEssential(): bool
	{
		return true;
	}

	public function isList(): bool
	{
		return false;
	}
}
