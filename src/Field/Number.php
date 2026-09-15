<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;
use Meraki\Schema\Field\Number\Value;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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
 * `$scale` says how many digits may follow the decimal point. It is a *restriction*, not a
 * normalisation: `123` and `123.00` are the same number, so a scale-2 field accepts both, and a
 * value that could not be held at that scale without discarding information — `123.456` at scale
 * 2 — fails the `scale` constraint. It used to fail the shape check instead, which reported "must
 * be a number" about a value that plainly was one.
 *
 * **Nothing here pads.** A field that yielded `123.00` for `123` was planned and never built; see
 * `transformed` in docs/ROADMAP.md. {@see Number\Value} holds the number as written, and the scale
 * is on the field for a consumer that wants to format against it — which is where formatting
 * belongs anyway, since how a number is written is a locale's business.
 *
 * @extends AtomicField<float|int|string|null>
 */
final readonly class Number extends AtomicField
{
	/** `null` means unbounded, rather than a float sentinel no message could usefully print. */
	public ?BigDecimal $minValue;

	public ?BigDecimal $maxValue;

	/** `null` means any value is on-step. */
	public ?BigDecimal $step;

	/**
	 * The most significant digits a value may carry, counting from the first non-zero one;
	 * `null` means no ceiling.
	 *
	 * Not the same question as `$scale`, and neither implies the other. Scale counts digits
	 * *after the point* and is exact — a scale-2 field holds `12.34` and nothing finer.
	 * Precision counts significant digits wherever they fall and is a maximum — at precision 4,
	 * `12.34` and `0.001234` both fit, and `12.345` does not. A measurement reported "to four
	 * significant figures" is asking for this; a currency amount is asking for scale.
	 *
	 * Leading zeros never count, so `0.001234` is four digits. Trailing zeros do, because they
	 * are digits the author wrote: `1.0` is two.
	 */
	public ?int $maxPrecision;

	public function __construct(
		public FieldName $name,
		public ?int $scale = null,
	) {
		parent::__construct();

		$this->minValue = null;
		$this->maxValue = null;
		$this->step = null;
		$this->maxPrecision = null;

		// Last: every property it reads must already be set.
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * @throws InvalidArgumentException if negative
	 */
	public function scaleTo(?int $scale): static
	{
		if ($scale !== null && $scale < 0) {
			throw new InvalidArgumentException('Scale must be a non-negative integer');
		}

		return $this->with(['scale' => $scale]);
	}

	/**
	 * @param positive-int|null $digits `null` removes the ceiling
	 * @throws InvalidArgumentException if not positive
	 */
	public function maxPrecisionOf(?int $digits): static
	{
		if ($digits !== null && $digits < 1) {
			throw new InvalidArgumentException('Precision must be at least one significant digit.');
		}

		return $this->with(['maxPrecision' => $digits]);
	}

	public function minValueOf(float|int|string $value): static
	{
		return $this->with(['minValue' => $this->mustParse($value)]);
	}

	public function maxValueOf(float|int|string $value): static
	{
		return $this->with(['maxValue' => $this->mustParse($value)]);
	}

	public function inIncrementsOf(float|int|string $step): static
	{
		return $this->with(['step' => $this->mustParse($step)]);
	}

	protected function parse(mixed $value): ?Value
	{
		try {
			return new Value($this->mustParse($value));
		} catch (MathException | TypeError) {
			// A bool or an array is not a number that happens to be out of range; it is not
			// a number at all, which BigDecimal reports as a TypeError rather than a MathException.
			return null;
		}
	}

	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			// The bounds stay null when unset rather than becoming '', so a message can tell
			// "no minimum" from "a minimum of nothing".
			new Constraint('minValue', $this->checkMinValue(...), $this->minValue?->__toString()),
			new Constraint('maxValue', $this->checkMaxValue(...), $this->maxValue?->__toString()),
			new Constraint('step', $this->checkStep(...), $this->step?->__toString()),
			new Constraint('scale', $this->checkScale(...), $this->scale),
			new Constraint('maxPrecision', $this->checkMaxPrecision(...), $this->maxPrecision),
		);
	}

	/**
	 * The number as written, before any scaling. Everything that needs to compare values uses
	 * this, so a value that cannot be expressed at the field's scale still reaches the `scale`
	 * constraint rather than failing earlier as the wrong shape.
	 */
	private function mustParse(mixed $value): BigDecimal
	{
		return BigDecimal::of($value);
	}

	private function checkMinValue(Value $value): ?bool
	{
		$value = $value->number;

		return $this->minValue === null ? null : $value->isGreaterThanOrEqualTo($this->minValue);
	}

	private function checkMaxValue(Value $value): ?bool
	{
		$value = $value->number;

		return $this->maxValue === null ? null : $value->isLessThanOrEqualTo($this->maxValue);
	}

	private function checkStep(Value $value): ?bool
	{
		$value = $value->number;

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

			return $value->minus($from)->remainder($this->step)->isZero();
		} catch (MathException) {
			return false;
		}
	}

	private function checkMaxPrecision(Value $value): ?bool
	{
		$value = $value->number;

		if ($this->maxPrecision === null) {
			return null;
		}

		// Zero has no significant digits at all, so it fits any ceiling.
		return $value->getPrecision() <= $this->maxPrecision;
	}

	/**
	 * Whether the value can be held at this field's scale without discarding anything.
	 *
	 * Trailing zeros are stripped first, so `123.4500` counts as two decimal places rather than
	 * four — they carry no information the field would be dropping. That matches what the old
	 * `toScale(UNNECESSARY)` accepted, without needing an exception to find out.
	 */
	private function checkScale(Value $value): ?bool
	{
		$value = $value->number;

		if ($this->scale === null) {
			return null;
		}

		return $value->stripTrailingZeros()->getScale() <= $this->scale;
	}
}
