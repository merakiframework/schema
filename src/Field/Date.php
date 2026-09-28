<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\AtomicField;
use Meraki\Schema\Field\Date\Value;
use Meraki\Schema\FieldName;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\ValueScope;
use Brick\DateTime\LocalDate;
use Brick\DateTime\Period;

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
	/**
	 * The earliest date accepted, inclusive; `null` means no lower bound.
	 *
	 * A sentinel — `LocalDate::min()` — stood here before, which made an unset bound report as
	 * *passed* rather than *skipped*: the field answered a question nobody had asked. Every other
	 * optional bound in this library is `?T` with `null` meaning no limit, and these are no
	 * longer the exception.
	 */
	public ?LocalDate $from;

	/**
	 * The first date *out* of range, exclusive; `null` means no upper bound.
	 *
	 * There used to be an inclusive `to()` beside it, and it was removed because both reported
	 * under the name `until`, so a result could not say which had been declared. Two behaviours
	 * sharing one constraint name is worse than two names for one behaviour.
	 */
	public ?LocalDate $until;

	/**
	 * How far apart the accepted dates are, counted from {@see self::$from}.
	 *
	 * Meaningless without a lower bound to count from, so the constraint is skipped when `from`
	 * is unset rather than being measured against an arbitrary origin.
	 */
	public Period $interval;

	public function __construct(
		public FieldName $name,
	) {
		parent::__construct();

		$this->from = self::initially(null);
		$this->until = self::initially(null);
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
			new Constraint('from', $this->isOnOrAfterFrom(...), $this->from?->__toString()),
			new Constraint('until', $this->isBeforeUntil(...), $this->until?->__toString()),
			new Constraint('interval', $this->isOnAnInterval(...), (string) $this->interval),
		);
	}

	private function isOnOrAfterFrom(Value $parsed): ?bool
	{
		$date = $parsed->date;

		return $this->from === null ? null : $date->isAfterOrEqualTo($this->from);
	}

	private function isBeforeUntil(Value $parsed): ?bool
	{
		$date = $parsed->date;

		return $this->until === null ? null : $date->isBefore($this->until);
	}

	private function isOnAnInterval(Value $parsed): ?bool
	{
		$date = $parsed->date;

		// Nothing to count from, so nothing is being asked. See $interval.
		if ($this->from === null) {
			return null;
		}

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
