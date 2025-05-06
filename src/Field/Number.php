<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Property;

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
 *	- to only allow integers, set the 'step' attribute to 1
 *	- to only allow positive numbers, set the 'min' attribute to 0
 *	- to only allow negative numbers, set the 'max' attribute to 0
 *	- to force floats, set the step attribute to a decimal value (e.g. 0.1)
 */
final class Number extends AtomicField
{
	public float $min = (-PHP_FLOAT_MAX);

	public float $max = PHP_FLOAT_MAX;

	public float $step = 1.0;

	public function __construct(
		Property\Name $name,
		Property\Value $value = null,
		Property\Value $defaultValue = null,
		bool $optional = false,
	) {
		parent::__construct(new Property\Type('number'), $name, $value, $defaultValue, $optional);
	}

	public function minOf(float|int $minValue): self
	{
		$this->min = (float)$minValue;

		return $this;
	}

	public function maxOf(float|int $maxValue): self
	{
		$this->max = (float)$maxValue;

		return $this;
	}

	public function inIncrementsOf(float|int $step): self
	{
		$this->step = (float)$step;

		return $this;
	}

	protected function cast(string $value): int|float
	{
		if (ctype_digit($value)) {
			return (integer)$value;
		}

		return (float)$value;
	}

	protected function validateType(mixed $value): bool
	{
		return is_int($value) || is_float($value);
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
		return $value >= $this->min;
	}

	private function validateMax(mixed $value): bool
	{
		return $value <= $this->max;
	}

	private function validateStep(mixed $value): bool
	{
		if ($this->step === 0) {
			return true;
		}

		$diff = $value - $this->min;
		$quotient = $diff / $this->step;

		return abs($quotient - round($quotient)) < 1e-8;
	}
}
