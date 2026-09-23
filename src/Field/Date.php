<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\ValueScope;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;
use Brick\DateTime\Period;
use Meraki\Schema\Field\Date\Value;
use Brick\DateTime\LocalDate;

/**
 * A calendar date, written as `YYYY-MM-DD`.
 *
 * A point in time rather than a quantity, so it is bounded by `from`/`until` and recurs at an
 * *interval*. {@see Duration}, which is a length of time, takes value bounds and steps.
 *
 * @extends AtomicField<string|null>
 */
final readonly class Date extends AtomicField
{
	/** Inclusive. */
	public LocalDate $from;

	/**
	 * Exclusive: a date equal to this one is *out* of range.
	 *
	 * There used to be an inclusive `to()` beside it, and it is gone — both reported under the
	 * name `until`, so a result could not say which had been declared. Two behaviours sharing one
	 * constraint name is worse than two names for one behaviour. An inclusive bound is this one
	 * plus a day, which the author writes.
	 */
	public LocalDate $until;

	public Period $interval;

	public function __construct(
		public FieldName $name,
	) {
		parent::__construct();

		$this->from = self::initially(LocalDate::min());
		$this->until = self::initially(LocalDate::max());
		$this->interval = self::initially(Period::ofDays(1));
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * This is inclusive of the date provided.
	 */
	public function from(string $date): static
	{
		return $this->with(['from' => LocalDate::parse($date)]);
	}

	/**
	 * What a rule may ask about this field: a date can be ranked, and reads back as text.
	 *
	 * @see \Meraki\Schema\Rule\Matcher for the four sets and why a field declares one
	 */
	public function when(): Matcher\OrderedText
	{
		return new Matcher\OrderedText(ValueScope::of($this->name));
	}

	/**
	 * This is exclusive of the date provided.
	 */
	public function until(string $date): static
	{
		return $this->with(['until' => LocalDate::parse($date)]);
	}

	public function atIntervalsOf(string $date): static
	{
		return $this->with(['interval' => Period::parse($date)]);
	}

	protected function parse(mixed $value): Value
	{
		if ($value instanceof Value) {
			return $value;
		}

		if (!is_string($value)) {
			throw MalformedValue::of(Value::class, 'a date is submitted as a string');
		}

		return new Value($value);
	}

	/**
	 * Only ever called on a value that passed, so the parse cannot fail here.
	 */

	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			new Constraint('from', $this->isOnOrAfterFrom(...), (string) $this->from),
			new Constraint('until', $this->isBeforeUntil(...), (string) $this->until),
			new Constraint('interval', $this->isOnAnInterval(...), (string) $this->interval),
		);
	}

	private function isOnOrAfterFrom(Value $parsed): bool
	{
		$date = $parsed->date;

		return $date->isAfterOrEqualTo($this->from);
	}

	private function isBeforeUntil(Value $parsed): bool
	{
		$date = $parsed->date;

		return $date->isBefore($this->until);
	}

	private function isOnAnInterval(Value $parsed): bool
	{
		$date = $parsed->date;

		if ($date->isEqualTo($this->from)) {
			return true;
		}

		// Day-based intervals (including the P1D default): the number of days from `from` must
		// be a whole multiple of the interval.
		if ($this->interval->getYears() === 0 && $this->interval->getMonths() === 0) {
			$intervalDays = $this->interval->getDays();

			if ($intervalDays <= 0) {
				return false;
			}

			return $this->from->daysUntil($date) % $intervalDays === 0;
		}

		// Month/year based intervals: step from `from` until we land on or pass the value.
		$cursor = $this->from;

		while ($cursor->isBeforeOrEqualTo($date)) {
			if ($cursor->isEqualTo($date)) {
				return true;
			}

			$cursor = $cursor->plusPeriod($this->interval);
		}

		return false;
	}
}
