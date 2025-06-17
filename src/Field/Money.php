<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use Meraki\Schema\Field;
use Meraki\Schema\Field\Composite as CompositeField;
use Meraki\Schema\Property;

/**
 * @extends Serialized<string|null>
 * @property-read array<string, string> $min
 * @property-read array<string, string> $max
 * @property-read array<string, string> $step
 * @property-read array<string, int> $scale
 * @property-read array<string> $allowed
 * @internal
 */
interface SerializedMoney extends Serialized
{
}

/**
 * @extends CompositeField<array|null, SerializedMoney>
 * @property-read Field\Enum $currency
 * @property-read Field\Number $amount
 */
final class Money extends CompositeField
{
	/**
	 * @var array<string, BigDecimal>
	 * @readonly
	 */
	public array $min = [];

	/**
	 * @var array<string, BigDecimal>
	 * @readonly
	 */
	public array $max = [];

	/**
	 * @var array<string, BigDecimal>
	 * @readonly
	 */
	public array $step = [];

	/**
	 * @var array<string, int>
	 * @readonly
	 */
	public array $scale = [];

	/**
	 * @var array<string>
	 * @readonly
	 */
	public array $allowed = [];

	/** @param non-empty-array<string, int> $allowedCurrencies */
	public function __construct(
		Property\Name $name,
		array $allowedCurrencies = [],
	) {
		$this->scale = array_change_key_case($allowedCurrencies, CASE_UPPER);
		$this->allowed = array_keys($this->scale);

		parent::__construct(
			new Property\Type('money', $this->validateType(...)),
			$name,
			new Field\Enum(new Property\Name('currency'), $this->allowed),
			(new Field\Number(new Property\Name('amount')))->inIncrementsOf(0),		// just a dummy field essentially
		);
	}

	public function allow(string $currency, int $scale): self
	{
		$currency = strtoupper($currency);
		$this->scale[$currency] = $scale;

		// only need to add if not already exists
		if (!in_array($currency, $this->allowed, true)) {
			$this->allowed[] = $currency;
			$this->currency->allow($currency);
		}

		return $this;
	}

	public function minOf(string $currency, string $amount): self
	{
		$this->assertCurrencyIsAllowed($currency);

		$this->min[strtoupper($currency)] = $this->toDecimal($currency, $amount);

		return $this;
	}

	public function maxOf(string $currency, string $amount): self
	{
		$this->assertCurrencyIsAllowed($currency);

		$this->max[strtoupper($currency)] = $this->toDecimal($currency, $amount);

		return $this;
	}

	public function inIncrementsOf(string $currency, string $amount): self
	{
		$this->assertCurrencyIsAllowed($currency);

		$step = $this->toDecimal($currency, $amount);

		if ($step->isNegative()) {
			throw new InvalidArgumentException("Step for currency '{$currency}' cannot be negative.");
		}

		$this->step[strtoupper($currency)] = $step;

		return $this;
	}

	protected function getConstraints(): array
	{
		$currencyName = $this->getCurrencyFieldName();
		$amountName = $this->getAmountFieldName();

		return [
			"{$amountName}.scale" => $this->validateScale(...),
			"{$amountName}.min" => $this->validateMin(...),
			"{$amountName}.max" => $this->validateMax(...),
			"{$amountName}.step" => $this->validateStep(...),
		];
	}

	private function validateStep(array $value): ?bool
	{
		[$currency, $amount] = $this->normalizeValue($value);

		// step not set or min not set, skip validation
		if (!isset($this->step[$currency]) || !isset($this->min[$currency])) {
			return null;
		}

		$min = $this->min[$currency];
		$step = $this->step[$currency];

		if ($step->isZero()) {
			return true;
		}

		try {
			$value = $this->toDecimal($currency, $amount);
			$diff = $value->minus($min);

			return $diff->remainder($step)->isZero();
		} catch (MathException|TypeError|RoundingNecessaryException) {
			return false;
		}
	}

	private function validateMax(array $value): ?bool
	{
		[$currency, $amount] = $this->normalizeValue($value);

		// not set, skip validation
		if (!isset($this->max[$currency])) {
			return null;
		}

		try {
			return $this->toDecimal($currency, $amount)->isLessThanOrEqualTo($this->max[$currency]);
		} catch (MathException|TypeError | RoundingNecessaryException) {
			return false;
		}
	}

	private function validateMin(array $value): ?bool
	{
		[$currency, $amount] = $this->normalizeValue($value);

		// not set, skip validation
		if (!isset($this->min[$currency])) {
			return null;
		}

		try {
			return $this->toDecimal($currency, $amount)->isGreaterThanOrEqualTo($this->min[$currency]);
		} catch (MathException|TypeError|RoundingNecessaryException) {
			return false;
		}
	}

	private function validateScale(array $value): ?bool
	{
		[$currency, $amount] = $this->normalizeValue($value);

		if (!isset($this->scale[$currency])) {
			return null;
		}

		try {
			return $this->toDecimal($currency, $amount)->getScale() === $this->scale[$currency];
		} catch (MathException|TypeError|RoundingNecessaryException) {
			return false;
		}
	}

	/**
	 * @return array{string, string}
	 */
	private function normalizeValue(array $value): array
	{
		return [strtoupper($value[$this->getCurrencyFieldName()]), $value[$this->getAmountFieldName()]];
	}

	private function assertCurrencyIsAllowed(string $currency): void
	{
		if (!in_array(strtoupper($currency), $this->allowed, true)) {
			throw new InvalidArgumentException("Currency '{$currency}' is not allowed.");
		}
	}

	private function toDecimal(string $currency, string $amount): BigDecimal
	{
		return BigDecimal::of($amount)->toScale($this->scale[strtoupper($currency)], RoundingMode::UNNECESSARY);
	}

	private function getCurrencyFieldName(): string
	{
		return (new Property\Name('currency'))->prefixWith($this->name)->__toString();
	}

	private function getAmountFieldName(): string
	{
		return (new Property\Name('amount'))->prefixWith($this->name)->__toString();
	}

	public function serialize(): SerializedMoney
	{
		$currencyFieldName = $this->getCurrencyFieldName();
		$amountFieldName = $this->getAmountFieldName();
		$value = $this->defaultValue->unwrap();

		return new class(
			type: $this->type->value,
			name: $this->name->value,
			optional: $this->optional,
			allowed: $this->allowed,
			min: self::flatten($this->min),
			max: self::flatten($this->max),
			step: self::flatten($this->step),
			scale: $this->scale,
			value: [
				$currencyFieldName => $value[$currencyFieldName],
				$amountFieldName => $value[$amountFieldName] !== null ? (string)$this->toDecimal($value[$currencyFieldName], $value[$amountFieldName]) : null,
			],
			fields: array_map(
				fn(Field $field): Serialized => $field->serialize(),
				$this->fields->getIterator()->getArrayCopy()
			),
		) implements SerializedMoney {
			public function __construct(
				public readonly string $type,
				public readonly string $name,
				public readonly bool $optional,
				/** @var array<string> */
				public readonly array $allowed,
				/** @var array<string, string> */
				public readonly array $min,
				/** @var array<string, string> */
				public readonly array $max,
				/** @var array<string, string> */
				public readonly array $step,
				/** @var array<string, int> */
				public readonly array $scale,
				public readonly array $value,
				/** @var array<Serialized> */
				public readonly array $fields,
			) {}
		};
	}

	/**
	 * Flattens the array of currency amounts to a string representation.
	 *
	 * @param array<string, BigDecimal> $array
	 * @return array<string, string>
	 */
	private static function flatten(array $array): array
	{
		$flattened = [];
		foreach ($array as $currency => $amount) {
			$flattened[strtoupper($currency)] = (string)$amount;
		}
		return $flattened;
	}

	/**
	 * @param SerializedMoney $data
	 * @throws InvalidArgumentException
	 */
	public static function deserialize(Serialized $data): static
	{
		if ($data->type !== 'money') {
			throw new InvalidArgumentException('Expected instance of SerializedMoney');
		}

		$deserializedChildren = array_map(Field::deserialize(...), $data->fields);
		$field = new self(
			new Property\Name($data->name),
			self::combineAllowedAndScale($data->allowed, $data->scale),
		);
		$field->fields = new Field\Set(...$deserializedChildren);
		$field->optional = $data->optional;

		foreach ($data->min as $currency => $amount) {
			$field->minOf($currency, $amount);
		}

		foreach ($data->max as $currency => $amount) {
			$field->maxOf($currency, $amount);
		}

		foreach ($data->step as $currency => $amount) {
			$field->inIncrementsOf($currency, $amount);
		}

		return $field->prefill($data->value);
	}

	/** combine $allowed and $scale together into array that constructor accepts $allowedCurrencies */
	private static function combineAllowedAndScale(array $allowed, array $scale): array
	{
		$allowedCurrencies = [];
		foreach ($allowed as $currency) {
			$allowedCurrencies[$currency] = $scale[$currency];
		}
		return $allowedCurrencies;
	}
}
