<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Brick\Math\BigDecimal;
use Meraki\Schema\Field;
use Meraki\Schema\Field\Composite as CompositeField;
use Meraki\Schema\Property;

/**
 * @property-read Field\Enum $currency
 * @property-read Field\Number $amount
 */
final class Money extends CompositeField
{

	/** @var non-empty-array<string, int> $currencyScales */
	private array $currencyScales = [];

	public function __construct(
		Property\Name $name,
		/** @param non-empty-array<string, int> $allowedCurrencies */
		array $allowedCurrencies = [],
	) {
		parent::__construct(
			new Property\Type('money', $this->validateType(...)),
			$name,
			new Field\Enum(new Property\Name('currency'), array_map('strtoupper', array_values($allowedCurrencies))),
			new Field\Number(new Property\Name('amount')),
		);

		foreach ($allowedCurrencies as $currency => $scale) {
			$this->addCurrencyScale($currency, $scale);
		}

		$currencyName = (new Property\Name('currency'))->prefixWith($name)->__toString();
		$amountName = (new Property\Name('amount'))->prefixWith($name)->__toString();

		$this->defaultValue = new Property\Value([$currencyName => null, $amountName => null]);
		$this->value = new Property\Value([$currencyName => null, $amountName => null]);
	}

	public function allow(string $currency, int $scale): self
	{
		$this->currency->allow(strtoupper($currency));
		$this->addCurrencyScale($currency, $scale);

		return $this;
	}

	public function inIncrementsOf(string $amount): void
	{
	}

	protected function cast(string $value): mixed
	{
		return $value;
	}

	protected function getConstraints(): array
	{
		$currencyName = (new Property\Name('currency'))->prefixWith($this->name)->__toString();
		$amountName = (new Property\Name('amount'))->prefixWith($this->name)->__toString();

		return [
			"{$amountName}.scale" => $this->validateScale(...),
		];
	}

	private function validateScale(array $value): bool
	{
		$currencyName = (new Property\Name('currency'))->prefixWith($this->name)->__toString();
		$currency = strtoupper($value[$currencyName] ?? '');
		$amount = $value[(new Property\Name('amount'))->prefixWith($this->name)->__toString()];

		if (!isset($this->currencyScales[$currency])) {
			return false;
		}

		try {
			$decimal = BigDecimal::of($amount);
			return $decimal->getScale() <= $this->currencyScales[$currency];
		} catch (MathException | TypeError) {
			return false;
		}
	}

	private function addCurrencyScale(string $currency, int $scale): void
	{
		$this->currencyScales[strtoupper($currency)] = $scale;
	}
}
