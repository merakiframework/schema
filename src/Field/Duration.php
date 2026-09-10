<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Property;
use Brick\DateTime;
use Brick\DateTime\DateTimeException;

/**
 * A length of time, written in ISO 8601 duration form — `PT30M`, `P1DT2H`.
 *
 * A quantity rather than a point, so its bounds are on a *value* and it moves in *steps*.
 * The temporal fields, which name points in time, take `from`/`until` and recur at
 * intervals instead.
 *
 * @extends Field<string|null>
 */
final class Duration extends Field
{
	/**
	 * The authored baseline: a duration is a length of time within a day, counted in whole
	 * minutes. Narrow it, or widen the ceiling with maxValueOf().
	 */
	public private(set) ?DateTime\Duration $minValue;

	public private(set) ?DateTime\Duration $maxValue;

	public private(set) ?DateTime\Duration $step;

	public function __construct(
		public readonly Property\Name $name,
	) {
		$this->minValue = DateTime\Duration::zero();
		$this->maxValue = DateTime\Duration::ofDays(1);
		$this->step = DateTime\Duration::ofMinutes(1);
	}

	public function minValueOf(string $value): self
	{
		$this->minValue = $this->cast($value);

		return $this;
	}

	public function maxValueOf(string $value): self
	{
		$this->maxValue = $this->cast($value);

		return $this;
	}

	public function inIncrementsOf(string $value): self
	{
		$this->step = $this->cast($value);

		return $this;
	}

	public function validateValue(mixed $value): bool
	{
		if (!is_string($value)) {
			return false;
		}

		try {
			$this->cast($value);

			return true;
		} catch (DateTimeException) {
			return false;
		}
	}

	protected function cast(mixed $value): DateTime\Duration
	{
		return DateTime\Duration::parse($value);
	}

	public function constraints(): Constraint\Set
	{
		return (new Constraint\Set())
			->and('minValue', $this->checkMinValue(...), (string) $this->minValue)
			->and('maxValue', $this->checkMaxValue(...), (string) $this->maxValue)
			->and('step', $this->checkStep(...), (string) $this->step);
	}

	protected function getConstraints(): array
	{
		return [];
	}

	private function checkMinValue(mixed $value): ?bool
	{
		return $this->minValue === null ? null : $this->cast($value)->isGreaterThanOrEqualTo($this->minValue);
	}

	private function checkMaxValue(mixed $value): ?bool
	{
		return $this->maxValue === null ? null : $this->cast($value)->isLessThanOrEqualTo($this->maxValue);
	}

	private function checkStep(mixed $value): ?bool
	{
		if ($this->step === null) {
			return null;
		}

		// A zero step is a configuration error rather than a value problem, but it has always
		// been reported as a failure here — Number treats the same case as "no stepping".
		// The inconsistency is recorded in the API review; preserved for now.
		if ($this->step->isZero()) {
			return false;
		}

		// Steps are counted from the minimum when there is one, so a field starting at 5
		// minutes in steps of 10 accepts 5, 15, 25 rather than 10, 20, 30.
		$from = $this->minValue?->toNanos() ?? 0;

		return ($this->cast($value)->toNanos() - $from) % $this->step->toNanos() === 0;
	}
}
