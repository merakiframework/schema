<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Time\Precision;
use Meraki\Schema\Field\Time\PrecisionPolicy;
use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;
use Brick\DateTime\Duration;
use Brick\DateTime\LocalDate;
use Meraki\Schema\Field\Time\Value;
use Brick\DateTime\LocalTime;
use Brick\DateTime\TimeZone;
use Brick\DateTime\DateTimeException;
use Brick\Math\BigInteger;
use InvalidArgumentException;

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
	/** Inclusive. */
	public LocalTime $from;

	/** Inclusive. */
	public LocalTime $until;

	public Duration $interval;

	public function __construct(
		public FieldName $name,
		public Precision $precision = Precision::Minutes,
		public PrecisionPolicy $policy = PrecisionPolicy::Truncate,
	) {
		parent::__construct();

		$this->from = LocalTime::min();
		$this->until = LocalTime::max();
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
		return $this->with(['from' => $this->mustParse($value)]);
	}

	/**
	 * This is inclusive of the time provided.
	 */
	public function until(string $value): static
	{
		return $this->with(['until' => $this->mustParse($value)]);
	}

	/**
	 * Accepts only times falling on the given interval from {@see self::$from}, written as an
	 * ISO 8601 duration.
	 *
	 * @throws InvalidArgumentException when the interval is finer than the precision, which
	 *         would accept times the field cannot represent
	 */
	public function atIntervalsOf(string $value): static
	{
		$interval = Duration::parse($value);
		$hasSeconds = $interval->toSecondsPart() !== 0;
		$hasNanos = $interval->toNanosPart() !== 0;

		if ($this->precision === Precision::Minutes && ($hasSeconds || $hasNanos)) {
			throw new InvalidArgumentException('Cannot step in seconds or nanoseconds when precision is in minutes.');
		}

		if ($this->precision === Precision::Seconds && $hasNanos) {
			throw new InvalidArgumentException('Cannot step in nanoseconds when precision is in seconds.');
		}

		return $this->with(['interval' => $interval]);
	}

	protected function parse(mixed $value): ?Value
	{
		if (!is_string($value)) {
			return null;
		}

		try {
			return new Value($this->mustParse($value));
		} catch (DateTimeException) {
			return null;
		}
	}

	/**
	 * Only ever called on a value that passed, so the parse cannot fail here.
	 */

	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			new Constraint('from', $this->isOnOrAfterFrom(...), (string) $this->from),
			new Constraint('until', $this->isOnOrBeforeUntil(...), (string) $this->until),
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

	private function isOnOrAfterFrom(Value $parsed): bool
	{
		$time = $parsed->time;

		return $time->isAfterOrEqualTo($this->from);
	}

	private function isOnOrBeforeUntil(Value $parsed): bool
	{
		$time = $parsed->time;

		return $time->isBeforeOrEqualTo($this->until);
	}

	private function isOnAnInterval(Value $parsed): bool
	{
		$time = $parsed->time;

		// Nanoseconds are computed inline rather than through intermediate helpers, which
		// coerce to float on large multiplications and lose the low digits.
		$input = $this->instantOf($time);
		$from = $this->instantOf($this->from);

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
