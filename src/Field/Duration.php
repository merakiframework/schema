<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\ValueScope;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;
use Meraki\Schema\Field\Duration\Value;
use Brick\DateTime;
use Brick\DateTime\DateTimeException;

/**
 * A length of time, written in ISO 8601 duration form — `PT30M`, `P1DT2H`.
 *
 * A quantity rather than a point, so its bounds are on a *value* and it moves in *steps*.
 * The temporal fields, which name points in time, take `from`/`until` and recur at
 * intervals instead.
 *
 * @extends AtomicField<string|null>
 */
final readonly class Duration extends AtomicField
{
	/**
	 * What a rule may ask about this field: a length of time can be ranked, and reads back as text.
	 *
	 * @see \Meraki\Schema\Rule\Matcher for the four sets and why a field declares one
	 */
	public function when(): Matcher\OrderedText
	{
		return new Matcher\OrderedText(ValueScope::of($this->name));
	}

	/**
	 * The authored baseline: a duration is a length of time within a day, counted in whole
	 * minutes. Narrow it, or widen the ceiling with {@see self::maxValueOf()}.
	 *
	 * These are deliberate bounds, not "unset" — a duration field with no opinion at all would
	 * accept `P100Y`, which no form means.
	 */
	public ?DateTime\Duration $minValue;

	public ?DateTime\Duration $maxValue;

	public ?DateTime\Duration $step;

	public function __construct(
		public FieldName $name,
	) {
		parent::__construct();

		$this->minValue = DateTime\Duration::zero();
		$this->maxValue = DateTime\Duration::ofDays(1);
		$this->step = DateTime\Duration::ofMinutes(1);

		// Last: every property it reads must already be set.
		$this->constraints = $this->defineConstraints();
	}

	public function minValueOf(string $value): static
	{
		return $this->with(['minValue' => $this->mustParse($value)]);
	}

	public function maxValueOf(string $value): static
	{
		return $this->with(['maxValue' => $this->mustParse($value)]);
	}

	public function inIncrementsOf(string $value): static
	{
		return $this->with(['step' => $this->mustParse($value)]);
	}

	protected function parse(mixed $value): Value
	{
		if ($value instanceof Value) {
			return $value;
		}

		if (!is_string($value)) {
			throw MalformedValue::of(Value::class, 'a length of time is submitted as a string');
		}

		return new Value($value);
	}

	/**
	 * Only ever called on a value that passed, so the parse cannot fail here.
	 */

	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			new Constraint('minValue', $this->checkMinValue(...), $this->minValue?->__toString()),
			new Constraint('maxValue', $this->checkMaxValue(...), $this->maxValue?->__toString()),
			new Constraint('step', $this->checkStep(...), $this->step?->__toString()),
		);
	}

	private function mustParse(mixed $value): DateTime\Duration
	{
		return DateTime\Duration::parse($value);
	}

	private function checkMinValue(Value $parsed): ?bool
	{
		$value = $parsed->duration;

		return $this->minValue === null ? null : $value->isGreaterThanOrEqualTo($this->minValue);
	}

	private function checkMaxValue(Value $parsed): ?bool
	{
		$value = $parsed->duration;

		return $this->maxValue === null ? null : $value->isLessThanOrEqualTo($this->maxValue);
	}

	private function checkStep(Value $parsed): ?bool
	{
		$value = $parsed->duration;

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

		return ($value->toNanos() - $from) % $this->step->toNanos() === 0;
	}
}
