<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Property;
use Brick\Math\RoundingMode;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use InvalidArgumentException;
use TypeError;

/**
 * A number: integer, decimal or float.
 *
 * DO NOT use a number field for telephone numbers, postal codes, credit card numbers or
 * anything else that merely looks numeric — those are strings that happen to be digits, and
 * leading zeros matter in them.
 *
 * `$scale` fixes how many digits follow the decimal point. It is a *normalisation*, not a
 * restriction on what may be entered: `123` and `123.00` are the same number, so a scale-2
 * field accepts `123` and resolves it to `123.00`. A value that could not be expressed at
 * that scale without discarding information — `123.456` at scale 2 — fails the `scale`
 * constraint. It used to fail the shape check instead, which reported "must be a number"
 * about a value that plainly was one.
 *
 * @extends AtomicField<float|int|string|null>
 */
final class Number extends AtomicField
{
	/** `null` means unbounded, rather than a float sentinel no message could usefully print. */
	public private(set) ?BigDecimal $minValue = null;

	public private(set) ?BigDecimal $maxValue = null;

	/** `null` means any value is on-step. */
	public private(set) ?BigDecimal $step = null;

	public function __construct(
		public readonly Property\Name $name,
		public private(set) ?int $scale = null,
	) {
	}

	public function scaleTo(?int $scale): self
	{
		if ($scale !== null && $scale < 0) {
			throw new InvalidArgumentException('Scale must be a non-negative integer');
		}

		$this->scale = $scale;

		return $this;
	}

	public function minValueOf(float|int|string $value): self
	{
		$this->minValue = $this->parse($value);

		return $this;
	}

	public function maxValueOf(float|int|string $value): self
	{
		$this->maxValue = $this->parse($value);

		return $this;
	}

	public function inIncrementsOf(float|int|string $step): self
	{
		$this->step = $this->parse($step);

		return $this;
	}

	/**
	 * The number as written, before any scaling. Everything that needs to compare values
	 * uses this, so a value that cannot be expressed at the field's scale still reaches the
	 * `scale` constraint rather than failing earlier as the wrong shape.
	 */
	private function parse(mixed $value): BigDecimal
	{
		return BigDecimal::of($value);
	}

	protected function cast(mixed $value): BigDecimal
	{
		$value = $this->parse($value);

		if ($this->scale !== null) {
			$value = $value->toScale($this->scale, RoundingMode::UNNECESSARY);
		}

		return $value;
	}

	public function validateValue(mixed $value): bool
	{
		try {
			$this->parse($value);

			return true;
		} catch (MathException | TypeError) {
			// A bool or an array is not a number that happens to be out of range; it is not
			// a number at all, which BigDecimal reports as a TypeError rather than a MathException.
			return false;
		}
	}

	public function constraints(): Constraint\Set
	{
		return (new Constraint\Set())
			->and('minValue', $this->checkMinValue(...), (string) $this->minValue)
			->and('maxValue', $this->checkMaxValue(...), (string) $this->maxValue)
			->and('step', $this->checkStep(...), (string) $this->step)
			->and('scale', $this->checkScale(...), $this->scale);
	}

	protected function getConstraints(): array
	{
		return [];
	}

	private function checkMinValue(mixed $value): ?bool
	{
		return $this->minValue === null ? null : $this->parse($value)->isGreaterThanOrEqualTo($this->minValue);
	}

	private function checkMaxValue(mixed $value): ?bool
	{
		return $this->maxValue === null ? null : $this->parse($value)->isLessThanOrEqualTo($this->maxValue);
	}

	private function checkStep(mixed $value): ?bool
	{
		if ($this->step === null || $this->step->isZero()) {
			return null;
		}

		if ($this->step->isNegative()) {
			return false;
		}

		try {
			// Steps are counted from the minimum when there is one, so a field starting at
			// 5 in steps of 10 accepts 5, 15, 25 rather than 10, 20, 30.
			$from = $this->minValue ?? BigDecimal::zero();

			return $this->parse($value)->minus($from)->remainder($this->step)->isZero();
		} catch (MathException) {
			return false;
		}
	}

	private function checkScale(mixed $value): ?bool
	{
		if ($this->scale === null) {
			return null;
		}

		try {
			// UNNECESSARY throws rather than rounding, so this asks whether the value can be
			// held at this scale without discarding anything.
			$this->parse($value)->toScale($this->scale, RoundingMode::UNNECESSARY);

			return true;
		} catch (MathException) {
			return false;
		}
	}
}
