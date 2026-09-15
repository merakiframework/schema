<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Money;

use Meraki\Schema\Field\ParsedValue;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use InvalidArgumentException;
use TypeError;

/**
 * An amount of one currency, held together.
 *
 * The two halves are inseparable, which is why this exists: `12.50` means nothing until you know
 * whether it is dollars or yen, and a minimum of `7.00` can only be compared against an amount in
 * the same currency. Holding them as two fields made that pairing the caller's job to remember.
 *
 * The amount is a `BigDecimal` rather than a float, because money in a float is a bug waiting for
 * a big enough number. How many decimal places a currency uses is the *field's* business — see
 * `Money::allowCurrencies()` — since it varies: JPY has none, USD two, BHD three.
 *
 * ### Normalised, and not for printing
 *
 * This is the field's *internal* representation: what arrived, cleaned up so that everything
 * downstream reads one shape. `"aud "` and `"AUD"` both land here as `AUD`, which is what makes a
 * bound comparable against a submitted amount at all.
 *
 * So there is deliberately no `__toString()`. Rendering an amount is a locale's business — where
 * the symbol sits, which separators are used, whether the minor unit is shown — and guessing at it
 * here would be wrong in most of the world. An application that wants a string asks for one, from
 * something that knows the locale.
 */
final readonly class Value implements ParsedValue
{
	/**
	 * @param string $currency ISO 4217 alpha-3, upper-cased
	 * @throws InvalidArgumentException if the currency is not three letters
	 */
	public function __construct(
		public string $currency,
		public BigDecimal $amount,
	) {
		if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
			throw new InvalidArgumentException(
				"'{$currency}' is not an ISO 4217 currency code; three letters were expected.",
			);
		}
	}

	/**
	 * Whether this is the same money as another.
	 *
	 * Numeric on the amount, because `12.50` and `12.5` are the same money written two ways —
	 * `BigDecimal` keeps the scale it was given, so `==` would call them different. The currency is
	 * compared exactly; it is already upper-cased by then.
	 *
	 * This is the only value object that needs the method. The others hold strings, dates and
	 * integers, where structural comparison is already the right answer.
	 */
	public function equals(ParsedValue $other): bool
	{
		return $other instanceof self
			&& $this->currency === $other->currency
			&& $this->amount->isEqualTo($other->amount);
	}

	/**
	 * Reads the array a form submits.
	 *
	 * @param array<string, mixed> $parts
	 * @throws InvalidArgumentException if either half is missing or unreadable
	 */
	public static function fromInput(array $parts): self
	{
		foreach (['currency', 'amount'] as $key) {
			// array_key_exists rather than isset: a null here is a half-filled form, and saying so
			// is more useful than reporting the key as absent.
			if (!array_key_exists($key, $parts)) {
				throw new InvalidArgumentException("An amount of money is missing its \"{$key}\".");
			}
		}

		if (!is_string($parts['currency'])) {
			throw new InvalidArgumentException('A currency must be a string.');
		}

		try {
			$amount = BigDecimal::of($parts['amount']);
		} catch (MathException | TypeError) {
			throw new InvalidArgumentException('An amount must be a number.');
		}

		// Upper-cased because ISO 4217 defines the codes that way, so `aud` and `AUD` are one
		// code. Not trimmed: `'AUD '` is not a code, and repairing it is the port's job.
		return new self(strtoupper($parts['currency']), $amount);
	}

}
