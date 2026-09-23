<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Exception\InvalidConfiguration;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\ValueScope;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Field\DateTime\TimePrecision;
use Meraki\Schema\Field\DateTime\PrecisionPolicy;
use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;
use Brick\Math\BigInteger;
use Brick\DateTime\TimeZone;
use Brick\DateTime\DateTimeException;
use Meraki\Schema\Field\DateTime\Value;
use Brick\DateTime\LocalDateTime;
use Brick\DateTime\Duration;

/**
 * A date and time of day.
 *
 * `$precision` says how much of what was submitted is significant, and the caster says what to
 * do with the rest.
 *
 * A point in time rather than a quantity, so it is bounded by `from`/`until` and recurs at an
 * *interval*. {@see Duration}, which is a length of time, takes value bounds and steps.
 *
 * @extends AtomicField<string|null>
 */
final readonly class DateTime extends AtomicField
{
	/** Inclusive. */
	public LocalDateTime $from;

	/** Exclusive. */
	public LocalDateTime $until;

	public Duration $interval;

	public function __construct(
		public FieldName $name,
		public TimePrecision $precision = TimePrecision::Minutes,
		public PrecisionPolicy $policy = PrecisionPolicy::Truncate,
	) {
		parent::__construct();

		$this->from = LocalDateTime::min();
		$this->until = LocalDateTime::max();
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
		return $this->with(['from' => $this->mustParse($dateTime)]);
	}

	/**
	 * This is exclusive of the date-time provided.
	 */
	public function until(string $dateTime): static
	{
		return $this->with(['until' => $this->mustParse($dateTime)]);
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
			new Constraint('from', $this->isOnOrAfterFrom(...), (string) $this->from),
			new Constraint('until', $this->isBeforeUntil(...), (string) $this->until),
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

	private function isOnOrAfterFrom(Value $parsed): bool
	{
		$dateTime = $parsed->dateTime;

		return $dateTime->isAfterOrEqualTo($this->from);
	}

	private function isBeforeUntil(Value $parsed): bool
	{
		$dateTime = $parsed->dateTime;

		return $dateTime->isBefore($this->until);
	}

	private function isOnAnInterval(Value $parsed): bool
	{
		$dateTime = $parsed->dateTime;

		// Nanoseconds are computed inline rather than through intermediate helpers, which
		// coerce to float on large multiplications and lose the low digits.
		$input = $this->nanosOf($dateTime);
		$from = $this->nanosOf($this->from);

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
