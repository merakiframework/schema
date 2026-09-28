<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\AtomicField;
use Meraki\Schema\Exception\InvalidConfiguration;
use Meraki\Schema\Field\Time\Precision;
use Meraki\Schema\Field\Time\PrecisionPolicy;
use Meraki\Schema\Field\Time\Value;
use Meraki\Schema\FieldName;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\ValueScope;
use Brick\DateTime\Duration;
use Brick\DateTime\LocalDate;
use Brick\DateTime\LocalTime;
use Brick\DateTime\TimeZone;
use Brick\Math\BigInteger;

/**
 * A time of day, as close to ISO 8601, RFC 3339/9557 and the HTML standards as they allow.
 *
 * The HTML standard has no time format that intersects exactly with ISO 8601 or RFC 3339/9557,
 * so `$precision` says how much of what was submitted is significant, and the caster says what
 * to do with the rest.
 *
 * A point in time rather than a quantity, so it is bounded by `from`/`until` and recurs at an
 * *interval*. {@see Duration}, which is a length of time, takes value bounds and steps.
 *
 * @extends AtomicField<string|null>
 */
final readonly class Time extends AtomicField
{
	/**
	 * The earliest time accepted, inclusive; `null` means no lower bound.
	 *
	 * @see \Meraki\Schema\Field\Date::$from for why these are nullable rather than sentinels
	 */
	public ?LocalTime $from;

	/**
	 * The first time *out* of range, exclusive; `null` means no upper bound.
	 *
	 * This was inclusive until now, which made it disagree with {@see Date::$until} and
	 * {@see DateTime::$until} while all three reported under the same constraint name — so one
	 * sentence in a language pack was right for two fields and wrong for this one.
	 *
	 * A sentinel would have been especially wrong here. `LocalTime::max()` is
	 * `23:59:59.999999999`, a time a field at {@see Precision::Nanoseconds} can genuinely hold,
	 * so an exclusive bound defaulting to it would have refused the last instant of the day
	 * while claiming to be unbounded. `LocalDate::max()` is year 999999 and hides the same bug.
	 */
	public ?LocalTime $until;

	/**
	 * The earliest time accepted, exclusive; `null` means no lower bound.
	 *
	 * The counterpart to {@see self::$from}, and mutually exclusive with it.
	 */
	public ?LocalTime $after;

	/**
	 * The last time accepted, inclusive; `null` means no upper bound.
	 *
	 * Mutually exclusive with {@see self::$until}. Stored as the author wrote it rather than
	 * folded into `until` plus one granule: the definition serialises, and a reader in another
	 * language has to render back the bound that was declared, not one this library computed.
	 */
	public ?LocalTime $through;

	/**
	 * How far apart the accepted times are, counted from {@see self::$from}.
	 *
	 * Meaningless without a lower bound to count from, so the constraint is skipped when `from`
	 * is unset rather than being measured against an arbitrary origin.
	 */
	public Duration $interval;

	public function __construct(
		public FieldName $name,
		public Precision $precision = Precision::Minutes,
		public PrecisionPolicy $policy = PrecisionPolicy::Truncate,
	) {
		parent::__construct();

		$this->from = self::initially(null);
		$this->until = self::initially(null);
		$this->after = self::initially(null);
		$this->through = self::initially(null);
		$this->interval = match ($precision) {
			Precision::Minutes => Duration::ofMinutes(1),
			Precision::Seconds => Duration::ofSeconds(1),
			default => Duration::ofNanos(1),
		};

		// Last: every property it reads must already be set.
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * This is inclusive of the time provided.
	 */
	public function from(string $value): static
	{
		return $this->with(['from' => $this->mustParse($value), 'after' => null]);
	}

	/**
	 * Accepts anything later than this time, but not the time itself.
	 */
	public function after(string $value): static
	{
		return $this->with(['after' => $this->mustParse($value), 'from' => null]);
	}

	/**
	 * This is exclusive of the time provided: a time equal to it is out of range.
	 */
	public function until(string $value): static
	{
		return $this->with(['until' => $this->mustParse($value), 'through' => null]);
	}

	/**
	 * Accepts this time and anything earlier.
	 *
	 * The inclusive upper bound. Use {@see self::until()} for adjacent ranges, which tile
	 * without gaps precisely because they exclude their end.
	 */
	public function through(string $value): static
	{
		return $this->with(['through' => $this->mustParse($value), 'until' => null]);
	}

	/**
	 * What a rule may ask about this field: a time can be ranked, and reads back as text.
	 *
	 * @see \Meraki\Schema\Rule\Matcher for the four sets and why a field declares one
	 */
	public function when(): Matcher\OrderedText
	{
		return new Matcher\OrderedText(ValueScope::of($this->name));
	}

	/**
	 * Accepts only times falling on the given interval from {@see self::$from}, written as an
	 * ISO 8601 duration.
	 *
	 * @throws InvalidConfiguration when the interval is finer than the precision, which
	 *         would accept times the field cannot represent
	 */
	public function atIntervalsOf(string $value): static
	{
		$interval = Duration::parse($value);
		$hasSeconds = $interval->toSecondsPart() !== 0;
		$hasNanos = $interval->toNanosPart() !== 0;

		if ($this->precision === Precision::Minutes && ($hasSeconds || $hasNanos)) {
			throw InvalidConfiguration::stepIsFinerThanMinutePrecision();
		}

		if ($this->precision === Precision::Seconds && $hasNanos) {
			throw InvalidConfiguration::stepIsFinerThanSecondPrecision();
		}

		return $this->with(['interval' => $interval]);
	}

	protected function parse(mixed $value): Value
	{
		if ($value instanceof Value) {
			return $value;
		}

		if (!is_string($value)) {
			throw MalformedValue::of(Value::class, 'a time, as HH:MM or HH:MM:SS is submitted as a string');
		}

		// Two steps, and they belong to different owners. The value says whether this is a
		// time at all; this field then applies the precision policy it was configured with,
		// which no value could know about.
		return new Value($this->policy->applyTo((new Value($value))->time, $this->precision));
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
			new Constraint('precision', $this->hasAcceptablePrecision(...), $this->precision->value),
		);
	}

	/**
	 * Whether the value is no finer than this field describes.
	 *
	 * Skipped when the caster truncates: nothing is being asked of the precision, because
	 * anything extra is discarded before any other constraint sees it.
	 */
	private function hasAcceptablePrecision(Value $parsed): ?bool
	{
		$time = $parsed->time;

		if (!$this->policy->rejectsExtraPrecision()) {
			return null;
		}

		return $this->precision->covers($time);
	}

	/**
	 * The field owns the parse, because the field is what knows which type it holds; the
	 * precision owns the granularity, and the policy owns what happens to the rest.
	 */
	private function mustParse(mixed $value): LocalTime
	{
		return $this->policy->applyTo(LocalTime::parse($value), $this->precision);
	}

	private function isOnOrAfterFrom(Value $parsed): ?bool
	{
		$time = $parsed->time;

		return $this->from === null ? null : $time->isAfterOrEqualTo($this->from);
	}

	/**
	 * Whichever lower bound was declared, for the things that need an origin rather than a
	 * verdict. The two are mutually exclusive, so at most one is ever set.
	 */
	private function lowerBound(): ?LocalTime
	{
		return $this->from ?? $this->after;
	}

	private function isAfterAfter(Value $parsed): ?bool
	{
		$time = $parsed->time;

		return $this->after === null ? null : $time->isAfter($this->after);
	}

	private function isBeforeUntil(Value $parsed): ?bool
	{
		$time = $parsed->time;

		return $this->until === null ? null : $time->isBefore($this->until);
	}

	private function isOnOrBeforeThrough(Value $parsed): ?bool
	{
		$time = $parsed->time;

		return $this->through === null ? null : $time->isBeforeOrEqualTo($this->through);
	}

	private function isOnAnInterval(Value $parsed): ?bool
	{
		$time = $parsed->time;

		$origin = $this->lowerBound();

		// Nothing to count from, so nothing is being asked. See $interval.
		if ($origin === null) {
			return null;
		}

		// Nanoseconds are computed inline rather than through intermediate helpers, which
		// coerce to float on large multiplications and lose the low digits.
		$input = $this->instantOf($time);
		$from = $this->instantOf($origin);

		$intervalNanos = BigInteger::of($this->interval->getSeconds())
			->multipliedBy(BigInteger::of(1_000_000_000))
			->plus(BigInteger::of($this->interval->getNanos()));

		if ($intervalNanos->isZero()) {
			return false;
		}

		return $input->minus($from)->remainder($intervalNanos)->isZero();
	}

	private function instantOf(LocalTime $time): BigInteger
	{
		$instant = $time->atDate(LocalDate::now(TimeZone::utc()))->atTimeZone(TimeZone::utc())->getInstant();

		return BigInteger::of($instant->getEpochSecond())
			->multipliedBy(BigInteger::of(1_000_000_000))
			->plus(BigInteger::of($instant->getNano()));
	}
}
