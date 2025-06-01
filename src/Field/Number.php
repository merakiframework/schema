<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Brick\Math\RoundingMode;
use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Property;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use TypeError;


/**
 * Represents a number input field.
 *
 * A number field can be an integer, decimal, or float.
 *
 * DO NOT use a number field for telephone numbers, postal codes,
 * dates, money, etc... Use (or create your own) specialized field
 * for those types of data. Failing that, use a text field with the
 * appropriate constraint attributes set.
 *
 * Use the constraint attributes to set the number type if you need
 * to restrict the type of number allowed. For example:
 *	- to only allow integers, set the 'scale' property to 0
 *	- to only allow positive numbers, set the 'min' property to 0
 *	- to only allow negative numbers, set the 'max' property to 0
 *	- to force decimals, set the scale property to more than 0
 *	- exponent notation is always converted to canonical decimal form (if safe to do so)
 */
final class Number extends AtomicField
{
	public BigDecimal $min;

	public BigDecimal $max;

	public BigDecimal $step;

	public function __construct(
		Property\Name $name,
		public readonly ?int $scale = null,
	) {
		parent::__construct(new Property\Type('number', $this->validateType(...)), $name);

		$this->min = BigDecimal::of(-PHP_FLOAT_MAX);
		$this->max = BigDecimal::of(PHP_FLOAT_MAX);
		$this->step = BigDecimal::one();
	}

	public function minOf(float|int|string $minValue): self
	{
		$this->min = $this->cast($minValue);

		return $this;
	}

	public function maxOf(float|int|string $maxValue): self
	{
		$this->max = $this->cast($maxValue);

		return $this;
	}

	public function inIncrementsOf(float|int|string $step): self
	{
		$this->step = $this->cast($step);

		return $this;
	}

	protected function cast(mixed $value): BigDecimal
	{
		$value = BigDecimal::of($value);

		if ($this->scale !== null) {
			$value = $value->toScale($this->scale, RoundingMode::UNNECESSARY);
		}

		return $value;
	}

	protected function validateType(mixed $value): bool
	{
		try {
			$this->cast($value);
			return true;
		} catch (MathException) {
			return false;
		} catch (TypeError) {
			return false;
		}
	}

	protected function getConstraints(): array
	{
		return [
			'min' => $this->validateMin(...),
			'max' => $this->validateMax(...),
			'step' => $this->validateStep(...),
		];
	}

	private function validateMin(mixed $value): bool
	{
		return $this->cast($value)->isGreaterThanOrEqualTo($this->min);
	}

	private function validateMax(mixed $value): bool
	{
		return $this->cast($value)->isLessThanOrEqualTo($this->max);
	}

	private function validateStep(mixed $value): bool
	{
		if ($this->step->isZero()) {
			return true;
		}

		if ($this->step->isNegative()) {
			return false;
		}

		try {
			$value = $this->cast($value);
			$diff = $value->minus($this->min);

			return $diff->remainder($this->step)->isZero();
		} catch (MathException $e) {
			return false;
		}
	}
}
