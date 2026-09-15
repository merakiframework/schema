<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Money\Value;
use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Money\ISOCurrencyProvider;
use Brick\Money\Exception\UnknownCurrencyException;
use InvalidArgumentException;

/**
 * An amount of money, held as one {@see Value} carrying both the currency and the amount.
 *
 * They are inseparable, which is the whole reason this is one field: `12.50` means nothing until
 * you know whether it is dollars or yen, and a minimum of `7.00` can only be compared against an
 * amount in the same currency. As two sub-fields, keeping that pairing straight was the caller's
 * job, and every constraint name embedded the field's own name — renaming `cost` rewrote
 * `cost.amount.min`.
 *
 * ### Every bound is per currency
 *
 * Because there is no exchange rate here and there should not be. A minimum of seven is seven
 * dollars or seven hundred yen, and which one depends on the currency submitted — so
 * {@see self::minAmountOf()} takes the currency with the amount, and the constraint skips when the
 * submitted currency has no bound of its own.
 *
 * ### Scale defaults to the currency, and is overridden deliberately
 *
 * How many decimal places an amount may carry is per currency, because ISO 4217 says so: JPY has
 * no minor unit, AUD and USD have two, BHD three, CLF four. So naming a currency is enough —
 * `allowCurrencies(['AUD', 'JPY'])` takes each one's own exponent.
 *
 * An override exists because "money" covers two different quantities:
 *
 * - A **settleable amount**, which is what moves between accounts. Always a whole number of minor
 *   units — you cannot pay half a cent — so the currency's own exponent is right.
 * - A **rate or unit price**: fuel at `$1.859`/L, electricity at `$0.2345`/kWh, an ad CPM at
 *   `$0.001234`. Denominated in a currency, finer than its minor unit, and multiplied by a
 *   quantity before anything is settled.
 *
 * The second needs `allowCurrencies(['AUD' => 3])`, and writing the number out is the point: an
 * amount finer than the currency allows is a typo far more often than it is intent, so it should
 * take a deliberate keystroke rather than being the default.
 *
 * @extends AtomicField<array<string, mixed>|Value|null>
 */
final readonly class Money extends AtomicField
{
	/**
	 * Currencies this field accepts, mapped to the decimal places each may carry — the currency's
	 * own ISO 4217 exponent unless the author overrode it.
	 *
	 * Always the resolved map, whichever shape was written. Empty accepts any currency, and then
	 * no scale or bound applies: free-form means free-form, so a currency typed into an
	 * unrestricted field is data rather than a rule to start enforcing a scale with.
	 *
	 * @var array<string, int<0, max>>
	 */
	public array $allowedCurrencies;

	/** @var array<string, BigDecimal> Keyed by currency; a currency with no entry has no floor. */
	public array $minAmounts;

	/** @var array<string, BigDecimal> Keyed by currency; a currency with no entry has no ceiling. */
	public array $maxAmounts;

	/**
	 * @param array<int|string, string|int> $allowedCurrencies see {@see self::allowCurrencies()}
	 *        for the two shapes this takes
	 * @throws InvalidArgumentException if a code is not a known ISO 4217 currency, or a scale
	 *         override is not a whole number of zero or more
	 */
	public function __construct(
		public FieldName $name,
		array $allowedCurrencies = [],
	) {
		parent::__construct();

		$this->allowedCurrencies = self::checked([], $allowedCurrencies);
		$this->minAmounts = [];
		$this->maxAmounts = [];
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * Adds to the accepted currencies. Accumulates, like every other `allow*()`.
	 *
	 * Two shapes, and they mix freely in one call:
	 *
	 * ```php
	 * $field->allowCurrencies(['AUD', 'JPY']);   // each currency's own ISO 4217 exponent
	 * $field->allowCurrencies(['AUD' => 3]);     // an override, for a unit price
	 * $field->allowCurrencies(['JPY', 'AUD' => 3]);
	 * ```
	 *
	 * A bare entry is a currency code; a keyed entry names the code and gives it a scale. Allowing
	 * the same currency twice keeps whichever was written last, so an override may follow a plain
	 * mention.
	 *
	 * @param array<int|string, string|int> $currencies
	 * @throws InvalidArgumentException if a code is not a known ISO 4217 currency, or a scale
	 *         override is not a whole number of zero or more
	 */
	public function allowCurrencies(array $currencies): static
	{
		return $this->with(['allowedCurrencies' => self::checked($this->allowedCurrencies, $currencies)]);
	}

	/**
	 * Accepts any currency again, which also drops every scale and bound — they are all stated per
	 * currency, and there is no longer a currency to state them against.
	 */
	public function clearAllowedCurrencies(): static
	{
		return $this->with(['allowedCurrencies' => [], 'minAmounts' => [], 'maxAmounts' => []]);
	}

	/**
	 * The least this field accepts *in the given currency*.
	 *
	 * @throws InvalidArgumentException if the currency is not allowed, the amount is not a number,
	 *         or it cannot be held at that currency's scale
	 */
	public function minAmountOf(string $currency, string $amount): static
	{
		[$currency, $decimal] = $this->bound($currency, $amount);

		if (isset($this->maxAmounts[$currency]) && $decimal->isGreaterThan($this->maxAmounts[$currency])) {
			throw new InvalidArgumentException("A minimum of {$amount} {$currency} cannot exceed its maximum.");
		}

		return $this->with(['minAmounts' => [$currency => $decimal] + $this->minAmounts]);
	}

	/**
	 * The most this field accepts *in the given currency*.
	 *
	 * @throws InvalidArgumentException if the currency is not allowed, the amount is not a number,
	 *         or it cannot be held at that currency's scale
	 */
	public function maxAmountOf(string $currency, string $amount): static
	{
		[$currency, $decimal] = $this->bound($currency, $amount);

		if (isset($this->minAmounts[$currency]) && $decimal->isLessThan($this->minAmounts[$currency])) {
			throw new InvalidArgumentException("A maximum of {$amount} {$currency} cannot be less than its minimum.");
		}

		return $this->with(['maxAmounts' => [$currency => $decimal] + $this->maxAmounts]);
	}

	/**
	 * @param array<string, mixed>|Value $value
	 */
	protected function parse(mixed $value): ?Value
	{
		if ($value instanceof Value) {
			return $value;
		}

		$parts = self::recordIn($value);

		if ($parts === null) {
			return null;
		}

		try {
			return Value::fromInput($parts);
		} catch (InvalidArgumentException) {
			// An amount missing its currency, or either half unreadable. Reported rather than
			// raised: this runs on a request.
			return null;
		}
	}

	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			new Constraint('allowedCurrencies', $this->isAnAllowedCurrency(...), array_keys($this->allowedCurrencies), 'currency'),
			// Every bound here is per currency, so the declared one is only knowable when a single
			// currency is allowed. `boundFor` supplies the one that actually applied, once the
			// submitted amount has named its currency.
			new Constraint(
				'minAmount',
				$this->meetsMinimum(...),
				$this->singleBound($this->minAmounts),
				'amount',
				fn(Value $money): ?string => $this->boundFor($this->minAmounts, $money),
			),
			new Constraint(
				'maxAmount',
				$this->meetsMaximum(...),
				$this->singleBound($this->maxAmounts),
				'amount',
				fn(Value $money): ?string => $this->boundFor($this->maxAmounts, $money),
			),
			new Constraint(
				'scale',
				$this->matchesScale(...),
				$this->singleScale(),
				'amount',
				fn(Value $money): ?int => $this->allowedCurrencies[$money->currency] ?? null,
			),
		);
	}

	private function isAnAllowedCurrency(Value $money): ?bool
	{
		return $this->allowedCurrencies === [] ? null : isset($this->allowedCurrencies[$money->currency]);
	}

	private function meetsMinimum(Value $money): ?bool
	{
		$min = $this->minAmounts[$money->currency] ?? null;

		return $min === null ? null : $money->amount->isGreaterThanOrEqualTo($min);
	}

	private function meetsMaximum(Value $money): ?bool
	{
		$max = $this->maxAmounts[$money->currency] ?? null;

		return $max === null ? null : $money->amount->isLessThanOrEqualTo($max);
	}

	/**
	 * Whether the amount fits the scale allowed for its currency — by default the currency's own
	 * minor unit, so no fractional yen and no half-cents.
	 *
	 * Trailing zeros are stripped first, so `12.5000` counts as two decimal places: they carry no
	 * information the currency would be losing.
	 */
	private function matchesScale(Value $money): ?bool
	{
		$scale = $this->allowedCurrencies[$money->currency] ?? null;

		return $scale === null ? null : $money->amount->stripTrailingZeros()->getScale() <= $scale;
	}

	/**
	 * A bound only has something to interpolate when one currency is allowed; with several it
	 * depends on which the submitted amount turns out to be.
	 *
	 * @param array<string, BigDecimal> $bounds
	 */
	private function singleBound(array $bounds): ?string
	{
		if (count($this->allowedCurrencies) !== 1) {
			return null;
		}

		$currency = array_key_first($this->allowedCurrencies);

		return isset($bounds[$currency]) ? (string) $bounds[$currency] : null;
	}

	/**
	 * The bound for the currency that was actually submitted, so a message can name the figure the
	 * amount should have reached rather than only that it fell short.
	 *
	 * @param array<string, BigDecimal> $bounds
	 */
	private function boundFor(array $bounds, Value $money): ?string
	{
		$bound = $bounds[$money->currency] ?? null;

		return $bound === null ? null : (string) $bound;
	}

	private function singleScale(): ?int
	{
		// Not reset(): it takes the array by reference, which counts as modifying a readonly
		// property even though it only reads.
		return count($this->allowedCurrencies) === 1
			? $this->allowedCurrencies[array_key_first($this->allowedCurrencies)]
			: null;
	}

	/**
	 * @return array{string, BigDecimal}
	 * @throws InvalidArgumentException
	 */
	private function bound(string $currency, string $amount): array
	{
		$currency = strtoupper(trim($currency));

		if (!isset($this->allowedCurrencies[$currency])) {
			throw new InvalidArgumentException(
				"'{$currency}' is not one of this field's currencies; allow it before giving it a bound.",
			);
		}

		try {
			$decimal = BigDecimal::of($amount);
		} catch (MathException) {
			throw new InvalidArgumentException("'{$amount}' is not a number.");
		}

		$scale = $this->allowedCurrencies[$currency];

		if ($decimal->stripTrailingZeros()->getScale() > $scale) {
			throw new InvalidArgumentException(
				"{$amount} cannot be held in {$currency}, which this field takes to {$scale} decimal place(s).",
			);
		}

		return [$currency, $decimal];
	}

	/**
	 * Resolves both shapes {@see self::allowCurrencies()} accepts into the one map the field holds.
	 *
	 * An integer key means the entry is a bare currency code and the scale comes from ISO 4217; a
	 * string key means the entry names the code and overrides the scale.
	 *
	 * @param array<string, int<0, max>> $existing
	 * @param array<int|string, string|int> $additional
	 * @return array<string, int<0, max>>
	 * @throws InvalidArgumentException
	 */
	private static function checked(array $existing, array $additional): array
	{
		foreach ($additional as $key => $value) {
			if (is_int($key)) {
				if (!is_string($value)) {
					throw new InvalidArgumentException(sprintf(
						'A currency code must be a string, %s given.',
						get_debug_type($value),
					));
				}

				$currency = self::knownCurrency($value);
				$existing[$currency] = self::isoScale($currency);

				continue;
			}

			$currency = self::knownCurrency($key);

			if (!is_int($value)) {
				throw new InvalidArgumentException(sprintf(
					"%s's scale must be a whole number of decimal places, %s given.",
					$currency,
					get_debug_type($value),
				));
			}

			if ($value < 0) {
				throw new InvalidArgumentException("{$currency} cannot have {$value} decimal places.");
			}

			$existing[$currency] = $value;
		}

		return $existing;
	}

	/**
	 * The upper-cased code, once ISO 4217 is known to have it.
	 *
	 * Checked here — where the author writes the definition — rather than per request, because an
	 * unknown currency in an allow-list is a typo with no input that could satisfy it. A *submitted*
	 * currency that is not real is a different matter: `allowedCurrencies` reports that, so the
	 * failure names the right thing.
	 *
	 * The three-letter guard runs first because {@see ISOCurrencyProvider} also resolves numeric
	 * codes — `36` is AUD — and this field's whole surface is alpha-3. Accepting the numeric form
	 * here would collide with the integer keys that mean "a bare code" in the input array.
	 *
	 * @throws InvalidArgumentException
	 */
	private static function knownCurrency(string $currency): string
	{
		$currency = strtoupper(trim($currency));

		if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
			throw new InvalidArgumentException(
				"'{$currency}' is not an ISO 4217 currency code; three letters were expected.",
			);
		}

		try {
			ISOCurrencyProvider::getInstance()->getCurrency($currency);
		} catch (UnknownCurrencyException) {
			throw new InvalidArgumentException("'{$currency}' is not a known ISO 4217 currency.");
		}

		return $currency;
	}

	/**
	 * The currency's own minor unit, which is the scale unless the author says otherwise.
	 *
	 * @return int<0, max>
	 */
	private static function isoScale(string $currency): int
	{
		/** @var int<0, max> */
		return ISOCurrencyProvider::getInstance()->getCurrency($currency)->getDefaultFractionDigits();
	}
}
