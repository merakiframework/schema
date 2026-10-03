<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Money;

use Meraki\Schema\Field;

/**
 * What a money field checks, by the code a failure is reported under.
 *
 * The first four decide whether what arrived is money at all, and are reported by {@see Input}
 * before anything else is asked. The rest decide whether this field accepts it.
 *
 * @see Field\Check for why a code is an enum rather than a string
 */
enum Check: string implements Field\Check
{
	case CurrencyRequired = 'currencyRequired';
	case AmountRequired = 'amountRequired';
	case CurrencyFormat = 'currencyFormat';
	case AmountFormat = 'amountFormat';
	case KnownCurrency = 'knownCurrency';
	case AllowedCurrencies = 'allowedCurrencies';
	case MinAmount = 'minAmount';
	case MaxAmount = 'maxAmount';
	case Scale = 'scale';

	public function part(): Part
	{
		return match ($this) {
			self::CurrencyRequired, self::CurrencyFormat, self::KnownCurrency, self::AllowedCurrencies => Part::Currency,
			self::AmountRequired, self::AmountFormat, self::MinAmount, self::MaxAmount, self::Scale => Part::Amount,
		};
	}
}
