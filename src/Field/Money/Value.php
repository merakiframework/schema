<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Money;

use Meraki\Schema\Exception\IncomparableValues;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Comparison\Comparable;
use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Comparison\Order;
use Meraki\Schema\Field\HasParts;
use Meraki\Schema\Field\ParsedValue;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;

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
final readonly class Value implements ParsedValue, HasParts, Comparable
{
	/** ISO 4217 alpha-3, upper-cased. */
	public string $currency;

	/** The amount, at whatever scale it was written with. */
	public BigDecimal $amount;

	/**
	 * Takes the record a field takes, which is the rule everywhere: a value is made of exactly
	 * what the field accepts, so there is one answer to "what is money here" rather than a
	 * field that reads input and a value that trusts whatever it is handed.
	 *
	 * {@see self::of()} is the readable way to write one by hand — a rule's bound, a test —
	 * and it is a convenience over this rather than a second way in.
	 *
	 * @param object $money with a `currency` and an `amount`
	 * @throws MalformedValue if either half is missing or unreadable
	 */
	public function __construct(object $money)
	{
		$parts = get_object_vars($money);

		foreach (['currency', 'amount'] as $key) {
			// array_key_exists rather than isset: a null here is a half-filled form, and saying
			// so is more useful than reporting the key as absent.
			if (!array_key_exists($key, $parts)) {
				throw MalformedValue::of(self::class, "it has no \"{$key}\"");
			}
		}

		if (!is_string($parts['currency'])) {
			throw MalformedValue::of(self::class, 'a currency is a string');
		}

		// Upper-cased because ISO 4217 defines the codes that way, so `aud` and `AUD` are one
		// code. Not trimmed: `'AUD '` is not a code, and repairing it is the port's job.
		$currency = strtoupper($parts['currency']);

		if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
			throw MalformedValue::of(self::class, sprintf(
				'"%s" is not an ISO 4217 currency code; three letters were expected',
				$parts['currency'],
			));
		}

		if (!is_float($parts['amount']) && !is_int($parts['amount']) && !is_string($parts['amount'])) {
			throw MalformedValue::of(self::class, 'an amount is a number or a string');
		}

		try {
			$this->amount = BigDecimal::of($parts['amount']);
		} catch (MathException) {
			throw MalformedValue::of(self::class, sprintf('"%s" is not an amount', $parts['amount']));
		}

		$this->currency = $currency;
	}

	/**
	 * The readable way to write an amount by hand.
	 *
	 *     $price->when()->isAtLeast(Money\\Value::of('AUD', '10.00'))
	 *
	 * A convenience over the constructor, not a second way in: it builds the same record a
	 * form would submit and hands it over, so there is one place where what money *is* gets
	 * decided.
	 *
	 * @throws MalformedValue if either half is unreadable
	 */
	public static function of(string $currency, BigDecimal|float|int|string $amount): self
	{
		return new self((object) [
			'currency' => $currency,
			'amount' => $amount instanceof BigDecimal ? (string) $amount : $amount,
		]);
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
	 * @throws IncomparableValues if the other value is not money, or is money in another
	 *         currency
	 */
	public function compareTo(Comparable $other): Order
	{
		if (!$other instanceof self) {
			throw IncomparableValues::money();
		}

		if ($this->currency !== $other->currency) {
			throw IncomparableValues::moneyInDifferentCurrencies($this->currency, $other->currency);
		}

		return Order::of($this->amount->compareTo($other->amount));
	}

	/**
	 * The two halves, which are the whole of what money is. A rule comparing currencies
	 * across two fields — "is the refund in the currency they paid in" — is the case this exists
	 * for.
	 *
	 * @return list<string>
	 */
	public static function partNames(): array
	{
		return ['currency', 'amount'];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function parts(): array
	{
		return [
			'currency' => $this->currency,
			'amount' => $this->amount,
		];
	}
}
