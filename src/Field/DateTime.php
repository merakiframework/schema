<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\AtomicField;
use Meraki\Schema\Exception\InvalidConfiguration;
use Meraki\Schema\Field\DateTime\PrecisionPolicy;
use Meraki\Schema\Field\DateTime\TimePrecision;
use Meraki\Schema\Field\DateTime\Value;
use Meraki\Schema\FieldName;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\ValueScope;
use Brick\DateTime\Duration;
use Brick\DateTime\LocalDateTime;
use Brick\DateTime\TimeZone;
use Brick\Math\BigInteger;

/**
 * A date and time of day.
 *
 * `$precision` says how much of what was submitted is significant, and the caster says what to
 * do with the rest.
 *
 * A point in time rather than a quantity, so it is bounded by `from`/`until` and recurs at an
 * *interval*. {@see Duration}, which is a length of time, takes value bounds and steps.
 *
 * ### This field shares no abstraction with its neighbours, deliberately
 *
 * {@see Date}, {@see Time} and {@see DateTime} are close to identical once the type names
 * are normalised, and they stay that way. A shared base or trait was considered and
 * declined: each is its own type and answers for itself, and the coupling would cost more
 * than the repetition saves. Brick offers no common supertype for `LocalDate`, `LocalTime`
 * and `LocalDateTime` either — they share only `Stringable` and `JsonSerializable`, not one
 * comparison method — so sharing would mean either loosening the bound types to `mixed` or
 * inventing a wrapper, and both are worse than two files that read straightforwardly.
 *
 * What the duplication actually risked was drift, and it drifted once: `until` was inclusive
 * on {@see Time} and exclusive on the other two, while all three reported under the one
 * constraint name. That is guarded now by
 * `tests/Api/TemporalBoundsTest.php`, which runs every bounds case against all three from one
 * provider. Copy a change to the siblings by hand, and let that test tell you if you miss one.
 *
 * @extends AtomicField<string|null>
 */
final readonly class DateTime extends AtomicField
{
	/**
	 * The earliest date-time accepted, inclusive; `null` means no lower bound.
	 *
	 * @see \Meraki\Schema\Field\Date::$from for why these are nullable rather than sentinels
	 */
	public ?LocalDateTime $from;

	/** The first date-time *out* of range, exclusive; `null` means no upper bound. */
	public ?LocalDateTime $until;

	/**
	 * The earliest date-time accepted, exclusive; `null` means no lower bound.
	 *
	 * The counterpart to {@see self::$from}, and mutually exclusive with it.
	 */
	public ?LocalDateTime $after;

	/**
	 * The last date-time accepted, inclusive; `null` means no upper bound.
	 *
	 * Mutually exclusive with {@see self::$until}. Stored as the author wrote it rather than
	 * folded into `until` plus one granule: the definition serialises, and a reader in another
	 * language has to render back the bound that was declared, not one this library computed.
	 */
	public ?LocalDateTime $through;

	/**
	 * How far apart the accepted date-times are, counted from {@see self::$from}.
	 *
	 * Meaningless without a lower bound to count from, so the constraint is skipped when `from`
	 * is unset rather than being measured against an arbitrary origin.
	 */
	public Duration $interval;

	public function __construct(
		public FieldName $name,
		public TimePrecision $precision = TimePrecision::Minutes,
		public PrecisionPolicy $policy = PrecisionPolicy::Truncate,
	) {
		parent::__construct();

		$this->from = self::initially(null);
		$this->until = self::initially(null);
		$this->after = self::initially(null);
		$this->through = self::initially(null);
		$this->interval = match ($precision) {
			TimePrecision::Minutes => Duration::ofMinutes(1),
			TimePrecision::Seconds => Duration::ofSeconds(1),
			default => Duration::ofNanos(1),
		};

		// Last: every property it reads must already be set.
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * This is inclusive of the date-time provided.
	 */
	public function from(string $dateTime): static
	{
		return $this->with(['from' => $this->mustParse($dateTime), 'after' => null]);
	}

	/**
	 * Accepts anything later than this date-time, but not the date-time itself.
	 */
	public function after(string $dateTime): static
	{
		return $this->with(['after' => $this->mustParse($dateTime), 'from' => null]);
	}

	/**
	 * This is exclusive of the date-time provided.
	 */
	public function until(string $dateTime): static
	{
		return $this->with(['until' => $this->mustParse($dateTime), 'through' => null]);
	}

	/**
	 * Accepts this date-time and anything earlier.
	 *
	 * The inclusive upper bound. Use {@see self::until()} for adjacent ranges, which tile
	 * without gaps precisely because they exclude their end.
	 */
	public function through(string $dateTime): static
	{
		return $this->with(['through' => $this->mustParse($dateTime), 'until' => null]);
	}

	/**
	 * What a rule may ask about this field: an instant can be ranked, and reads back as text.
	 *
	 * @see \Meraki\Schema\Rule\Matcher for the four sets and why a field declares one
	 */
	public function when(): Matcher\OrderedText
	{
		return new Matcher\OrderedText(ValueScope::of($this->name));
	}

	/**
	 * Accepts only date-times falling on the given interval from {@see self::$from}, written
	 * as an ISO 8601 duration.
	 *
	 * @throws InvalidConfiguration when the interval is finer than the precision, which
	 *         would accept date-times the field cannot represent
	 */
	public function atIntervalsOf(string $duration): static
	{
		$interval = Duration::parse($duration);
		$hasSeconds = $interval->toSecondsPart() !== 0;
		$hasNanos = $interval->toNanosPart() !== 0;

		if ($this->precision === TimePrecision::Minutes && ($hasSeconds || $hasNanos)) {
			throw InvalidConfiguration::stepIsFinerThanMinutePrecision();
		}

		if ($this->precision === TimePrecision::Seconds && $hasNanos) {
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
			throw MalformedValue::of(Value::class, 'a date and time, as YYYY-MM-DDTHH:MM is submitted as a string');
		}

		// Two steps, and they belong to different owners. The value says whether this is a
		// time at all; this field then applies the precision policy it was configured with,
		// which no value could know about.
		return new Value($this->policy->applyTo((new Value($value))->dateTime, $this->precision));
	}

	/**
	 * Only ever called on a value that passed, so the parse cannot fail here.
	 */

	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			new Constraint(DateTime\Check::From, $this->isOnOrAfterFrom(...), $this->from?->__toString()),
			new Constraint(DateTime\Check::After, $this->isAfterAfter(...), $this->after?->__toString()),
			new Constraint(DateTime\Check::Until, $this->isBeforeUntil(...), $this->until?->__toString()),
			new Constraint(DateTime\Check::Through, $this->isOnOrBeforeThrough(...), $this->through?->__toString()),
			new Constraint(DateTime\Check::Interval, $this->isOnAnInterval(...), (string) $this->interval),
			new Constraint(DateTime\Check::Precision, $this->hasAcceptablePrecision(...), $this->precision->value),
		);
	}

	protected static function declaredChecks(): array
	{
		return DateTime\Check::cases();
	}

	/**
	 * Whether the value is no finer than this field describes.
	 *
	 * Skipped when the caster truncates: nothing is being asked of the precision, because
	 * anything extra is discarded before any other constraint sees it.
	 */
	private function hasAcceptablePrecision(Value $parsed): ?bool
	{
		$dateTime = $parsed->dateTime;

		if (!$this->policy->rejectsExtraPrecision()) {
			return null;
		}

		return $this->precision->covers($dateTime);
	}

	/**
	 * The field owns the parse, because the field is what knows which type it holds; the
	 * precision owns the granularity, and the policy owns what happens to the rest.
	 */
	private function mustParse(mixed $value): LocalDateTime
	{
		return $this->policy->applyTo(LocalDateTime::parse($value), $this->precision);
	}

	private function isOnOrAfterFrom(Value $parsed): ?bool
	{
		$dateTime = $parsed->dateTime;

		return $this->from === null ? null : $dateTime->isAfterOrEqualTo($this->from);
	}

	/**
	 * Whichever lower bound was declared, for the things that need an origin rather than a
	 * verdict. The two are mutually exclusive, so at most one is ever set.
	 */
	private function lowerBound(): ?LocalDateTime
	{
		return $this->from ?? $this->after;
	}

	private function isAfterAfter(Value $parsed): ?bool
	{
		$dateTime = $parsed->dateTime;

		return $this->after === null ? null : $dateTime->isAfter($this->after);
	}

	private function isBeforeUntil(Value $parsed): ?bool
	{
		$dateTime = $parsed->dateTime;

		return $this->until === null ? null : $dateTime->isBefore($this->until);
	}

	private function isOnOrBeforeThrough(Value $parsed): ?bool
	{
		$dateTime = $parsed->dateTime;

		return $this->through === null ? null : $dateTime->isBeforeOrEqualTo($this->through);
	}

	private function isOnAnInterval(Value $parsed): ?bool
	{
		$dateTime = $parsed->dateTime;

		$origin = $this->lowerBound();

		// Nothing to count from, so nothing is being asked. See $interval.
		if ($origin === null) {
			return null;
		}

		// Nanoseconds are computed inline rather than through intermediate helpers, which
		// coerce to float on large multiplications and lose the low digits.
		$input = $this->nanosOf($dateTime);
		$from = $this->nanosOf($origin);

		$intervalNanos = BigInteger::of($this->interval->getSeconds())
			->multipliedBy(BigInteger::of(1_000_000_000))
			->plus(BigInteger::of($this->interval->getNanos()));

		if ($intervalNanos->isZero()) {
			return false;
		}

		return $input->minus($from)->remainder($intervalNanos)->isZero();
	}

	private function nanosOf(LocalDateTime $dateTime): BigInteger
	{
		$instant = $dateTime->atTimeZone(TimeZone::utc())->getInstant();

		return BigInteger::of($instant->getEpochSecond())
			->multipliedBy(BigInteger::of(1_000_000_000))
			->plus(BigInteger::of($instant->getNano()));
	}
}
