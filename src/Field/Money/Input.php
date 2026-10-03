<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Money;

use Meraki\Schema\Exception\BrokenInputContract;
use Meraki\Schema\Field;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Field\Violation;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;

/**
 * An amount of money as it was submitted, whether or not it is one yet: the currency and the
 * amount, each as read, and what stops them making a {@see Value}.
 *
 * The four things that decide whether this is money at all are here, and nothing else:
 *
 * | Code | The part | Wrong when |
 * | --- | --- | --- |
 * | `currencyRequired` | currency | it was not sent, or sent as `null` |
 * | `currencyFormat` | currency | it was sent and is not three letters |
 * | `amountRequired` | amount | it was not sent, or sent as `null` |
 * | `amountFormat` | amount | it was sent and is not a number |
 *
 * None of them reads the field's configuration, which is what makes them assembly rather than
 * constraints. Whether the currency is a real one, or one this field takes, is the field's to say
 * once there is money to say it about — see `Money::defineConstraints()`.
 *
 * Every part is read whatever happens to the other, so a form with both boxes wrong hears about
 * both at once.
 */
final readonly class Input implements Field\Input
{
	/**
	 * Three letters, upper-cased because ISO 4217 defines the codes that way; `null` when it was
	 * not sent or could not be read.
	 */
	public ?string $currency;

	/** The amount at the scale it was written with; `null` when it was not sent or is not a number. */
	public ?BigDecimal $amount;

	/** Money, once both halves are sound. */
	public ?Value $value;

	/** @var list<Violation> */
	public array $violations;

	/** @var list<Part> */
	public array $missingParts;

	/**
	 * Takes the record a field takes. {@see self::of()} is the way in from a whole value.
	 *
	 * @param object{currency?: string|null, amount?: string|int|float|null} $money
	 * @throws BrokenInputContract if it carries a key money does not have
	 * @throws MalformedValue if it holds nothing at all
	 */
	public function __construct(object $money)
	{
		$parts = get_object_vars($money);
		$names = array_column(Part::cases(), 'value');
		$unknown = array_diff(array_keys($parts), $names);

		// Raised, not reported. A key nobody declared is a port writing to the wrong contract,
		// and `ammount` is wrong on every request for every user — see
		// {@see \Meraki\Schema\Exception\BrokenInputContract} for why that is not a verdict.
		if ($unknown !== []) {
			throw BrokenInputContract::recordHasKeysItDoesNotAccept(Value::class, array_values($unknown), $names);
		}

		$currency = $parts['currency'] ?? null;
		$amount = $parts['amount'] ?? null;

		// Nothing in it at all is not money somebody has half written; it is not money. The field
		// reads that as unreadable, exactly as an empty address or an empty card is read.
		if ($currency === null && $amount === null) {
			throw MalformedValue::of(Value::class, 'it has neither a currency nor an amount');
		}

		$violations = [];
		$missing = [];

		if ($currency === null) {
			$violations[] = new Violation(Check::CurrencyRequired, true);
			$missing[] = Part::Currency;
		} else {
			$currency = self::currencyIn($currency);
			$violations = $currency === null ? [...$violations, new Violation(Check::CurrencyFormat)] : $violations;
		}

		if ($amount === null) {
			$violations[] = new Violation(Check::AmountRequired, true);
			$missing[] = Part::Amount;
		} else {
			$amount = self::amountIn($amount);
			$violations = $amount === null ? [...$violations, new Violation(Check::AmountFormat)] : $violations;
		}

		$this->currency = $currency;
		$this->amount = $amount;
		$this->violations = $violations;
		$this->missingParts = $missing;
		$this->value = ($currency !== null && $amount !== null) ? new Value($currency, $amount) : null;
	}

	/**
	 * The input a whole amount would have been read from, so a field handed its own value reads it
	 * the way it reads anything else.
	 */
	public static function of(Value $money): self
	{
		return new self((object) ['currency' => $money->currency, 'amount' => (string) $money->amount]);
	}

	/**
	 * The code, upper-cased, or null when it is not three letters.
	 *
	 * Not trimmed: `'AUD '` is not a code, and repairing it is the port's job. A blank one is not
	 * absent either — `''` was a decision somebody made, and reading it as "no currency" would let
	 * it stand in for one.
	 */
	private static function currencyIn(mixed $given): ?string
	{
		if (!is_string($given)) {
			return null;
		}

		$code = strtoupper($given);

		return preg_match('/^[A-Z]{3}$/', $code) === 1 ? $code : null;
	}

	/**
	 * The number, or null when it is not one. A float is read through its string form, which is
	 * what a person typing it would have written; a blank string is not a number.
	 */
	private static function amountIn(mixed $given): ?BigDecimal
	{
		if (!is_int($given) && !is_float($given) && !is_string($given)) {
			return null;
		}

		try {
			return BigDecimal::of($given);
		} catch (MathException) {
			return null;
		}
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

	/**
	 * Read the way the currency was: a rule written `equals('aud')` is about AUD, which is how the
	 * part is stored. The amount is compared numerically by the value it is compared against, so
	 * it is handed back as written.
	 */
	public function canonicalPartValue(Field\Part $part, mixed $expected): mixed
	{
		return ($part === Part::Currency && is_string($expected)) ? strtoupper($expected) : $expected;
	}
}
