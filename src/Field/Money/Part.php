<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Money;

use Meraki\Schema\Field;

/**
 * The two halves of an amount of money, which are the whole of what money is.
 *
 * Both are essential. `12.50` means nothing until you know whether it is dollars or yen, and a
 * currency with no amount is not an amount of anything.
 */
enum Part: string implements Field\Part
{
	case Currency = 'currency';
	case Amount = 'amount';

	public function isEssential(): bool
	{
		return true;
	}

	public function isList(): bool
	{
		return false;
	}
}
