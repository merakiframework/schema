<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Money;

use Meraki\Schema\Comparison\Comparable;
use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Comparison\Order;
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
final readonly class Value implements ParsedValue, Comparable
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
	 * Money in two currencies is never the same money, and asking is not a mistake — so this
	 * answers `false` where {@see self::compareTo()} raises. "Is 5 USD the same as 5 AUD" has an
	 * obvious answer; "which of them is larger" does not.
	 */
	public function equals(Equality $other): bool
	{
		return $other instanceof self
			&& $this->currency === $other->currency
			&& $this->amount->isEqualTo($other->amount);
	}

	/**
	 * Which is the larger amount — **within one currency only**.
	 *
	 * Two amounts in different currencies are not ordered. Ranking them needs an exchange rate,
	 * which is a fact about a moment in the market rather than about either value, and inventing
	 * one here would make `isAtLeast` quietly wrong rather than loudly unanswerable.
	 *
	 * Raising is already this interface's contract for "these cannot be compared" — a number
	 * refuses to be ordered against a date the same way. That is what lets money be
	 * {@see Comparable} at all: an `isAtLeast` on a money field is an obviously wanted rule, and
	 * excluding the whole type to avoid one raising case would have cost more than it saved.
	 *
	 * @throws InvalidArgumentException if the other value is not money, or is money in another
	 *         currency
	 */
	public function compareTo(Comparable $other): Order
	{
		if (!$other instanceof self) {
			throw new InvalidArgumentException('An amount of money can only be ordered against money.');
		}

		if ($this->currency !== $other->currency) {
			throw new InvalidArgumentException(sprintf(
				'%s and %s cannot be ordered: ranking them needs an exchange rate, which is not a '
				. 'property of either amount.',
				$this->currency,
				$other->currency,
			));
		}

		return Order::of($this->amount->compareTo($other->amount));
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
