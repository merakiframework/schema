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
	 * The earliest date accepted, exclusive; `null` means no lower bound.
	 *
	 * The counterpart to {@see self::$from}, and mutually exclusive with it — one bound said two
	 * ways, so setting either clears the other.
	 */
	public ?LocalDate $after;

	/**
	 * The first date *out* of range, exclusive; `null` means no upper bound.
	 *
	 * There used to be an inclusive `to()` beside it, and it was removed because both reported
	 * under the name `until`, so a result could not say which had been declared. That objection
	 * was about the constraint *name* rather than about offering the choice: {@see self::$through}
	 * is the inclusive bound under a name of its own, so a verdict says which was written.
	 */
	public ?LocalDate $until;

	/**
	 * The last date accepted, inclusive; `null` means no upper bound.
	 *
	 * Mutually exclusive with {@see self::$until}. Stored as the author wrote it rather than
	 * folded into `until` plus a day: the definition serialises, and a reader in another language
	 * has to be able to render back the bound that was declared, not one this library computed.
	 */
	public ?LocalDate $through;

	/**
	 * How far apart the accepted dates are, counted from whichever lower bound was declared.
	 *
	 * Meaningless without one to count from, so the constraint is skipped when neither
	 * {@see self::$from} nor {@see self::$after} is set, rather than being measured against an
	 * arbitrary origin.
	 */
	public Period $interval;

	public function __construct(
		public FieldName $name,
	) {
		parent::__construct();

		$this->from = self::initially(null);
		$this->after = self::initially(null);
		$this->until = self::initially(null);
		$this->through = self::initially(null);
		$this->interval = self::initially(Period::ofDays(1));
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * Accepts this date and anything later.
	 */
	public function from(string $date): static
	{
		return $this->with(['from' => LocalDate::parse($date), 'after' => null]);
	}

	/**
	 * Accepts anything later than this date, but not the date itself.
	 */
	public function after(string $date): static
	{
		return $this->with(['after' => LocalDate::parse($date), 'from' => null]);
	}

	/**
	 * Accepts anything earlier than this date, but not the date itself.
	 */
	public function until(string $date): static
	{
		return $this->with(['until' => LocalDate::parse($date), 'through' => null]);
	}

	/**
	 * Accepts this date and anything earlier.
	 *
	 * The inclusive upper bound, for the reading of "until" that most people have when the
	 * quantity is a whole day — "valid through 31 December" includes it. Use {@see self::until()}
	 * for adjacent ranges, which tile without gaps precisely because they exclude their end.
	 */
	public function through(string $date): static
	{
		return $this->with(['through' => LocalDate::parse($date), 'until' => null]);
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
			new Constraint('after', $this->isAfterAfter(...), $this->after?->__toString()),
			new Constraint('until', $this->isBeforeUntil(...), $this->until?->__toString()),
			new Constraint('through', $this->isOnOrBeforeThrough(...), $this->through?->__toString()),
			new Constraint('interval', $this->isOnAnInterval(...), (string) $this->interval),
		);
	}

	/**
	 * Whichever lower bound was declared, for the things that need an origin rather than a
	 * verdict. The two are mutually exclusive, so at most one is ever set.
	 */
	private function lowerBound(): ?LocalDate
	{
		return $this->from ?? $this->after;
	}

	private function isOnOrAfterFrom(Value $parsed): ?bool
	{
		$date = $parsed->date;

		return $this->from === null ? null : $date->isAfterOrEqualTo($this->from);
	}

	private function isAfterAfter(Value $parsed): ?bool
	{
		$date = $parsed->date;

		return $this->after === null ? null : $date->isAfter($this->after);
	}

	private function isBeforeUntil(Value $parsed): ?bool
	{
		$date = $parsed->date;

		return $this->until === null ? null : $date->isBefore($this->until);
	}

	private function isOnOrBeforeThrough(Value $parsed): ?bool
	{
		$date = $parsed->date;

		return $this->through === null ? null : $date->isBeforeOrEqualTo($this->through);
	}

	private function isOnAnInterval(Value $parsed): ?bool
	{
		$date = $parsed->date;
		$origin = $this->lowerBound();

		// Nothing to count from, so nothing is being asked. See $interval.
		if ($origin === null) {
			return null;
		}

		if ($date->isEqualTo($origin)) {
			return true;
		}

		// Day-based intervals (including the P1D default): the number of days from the origin must
		// be a whole multiple of the interval.
		if ($this->interval->getYears() === 0 && $this->interval->getMonths() === 0) {
			$intervalDays = $this->interval->getDays();

			if ($intervalDays <= 0) {
				return false;
			}

			return $origin->daysUntil($date) % $intervalDays === 0;
		}

		// Month/year based intervals: step from the origin until we land on or pass the value.
		$cursor = $origin;

		while ($cursor->isBeforeOrEqualTo($date)) {
			if ($cursor->isEqualTo($date)) {
				return true;
			}

			$cursor = $cursor->plusPeriod($this->interval);
		}

		return false;
	}
}
