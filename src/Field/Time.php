<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Time\PrecisionCaster;
use Meraki\Schema\Field\Time\Precision;
use Meraki\Schema\Field\Time\PreservePrecision;
use Meraki\Schema\Field\Time\TruncatePrecision;
use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;
use Brick\DateTime\Duration;
use Brick\DateTime\LocalDate;
use Brick\DateTime\LocalTime;
use Brick\DateTime\TimeZone;
use Brick\DateTime\DateTimeException;
use Brick\Math\BigInteger;
use InvalidArgumentException;

/**
 * A `time` value as close to ISO 8601, RFC 3339/9557, and HTML standards.
 *
 * The HTML standard does not have any time formats that have exact intersections
 * with the ISO 8601 and RFC 3339/9557 standards.
 *
 * @phpstan-import-type SerializedField from Field
 * @phpstan-type SerializedTime = SerializedField&object{
 * 	type: 'time',
 * 	from: string,
 * 	until: string,
 * 	step: string,
 * 	precision_unit: string,
 * 	precision_mode: string
 * }
 * @extends AtomicField<string|null, SerializedTime>
 */
final class Time extends AtomicField
{
	public LocalTime $from;

	public LocalTime $until;

	public Duration $step;

	public function __construct(
		Property\Name $name,
		public readonly Precision $precision = Precision::Minutes,
		private PrecisionCaster $caster = new TruncatePrecision(),
	) {
		parent::__construct(new Property\Type('time', $this->validateType(...)), $name);

		$this->from = LocalTime::min();
		$this->until = LocalTime::max();
		$this->step = match ($precision) {
			Precision::Minutes => Duration::ofMinutes(1),
			Precision::Seconds => Duration::ofSeconds(1),
			default => Duration::ofNanos(1),
		};
	}

	public static function withSecondPrecision(Property\Name $name): self
	{
		return new self($name, Precision::Seconds);
	}

	public static function withNanosecondPrecision(Property\Name $name): self
	{
		return new self($name, Precision::Nanoseconds);
	}

	public static function withMinutePrecision(Property\Name $name): self
	{
		return new self($name, Precision::Minutes);
	}

	/**
	 * This is inclusive of the date-time provided.
	 */
	public function from(string $value): self
	{
		$this->from = $this->cast($value);

		return $this;
	}

	/**
	 * Constrain value to be at intervals of the provided duration (in ISO 8601 format).
	 *
	 * @throws \InvalidArgumentException when trying to step in increments not allowed by the precision
	 */
	public function inIncrementsOf(string $value): self
	{
		$duration = Duration::parse($value);
		$hasSeconds = $duration->toSecondsPart() !== 0;
		$hasNanos = $duration->toNanosPart() !== 0;

		if ($this->precision === Precision::Minutes && ($hasSeconds || $hasNanos)) {
			throw new InvalidArgumentException('Cannot step in seconds or nanoseconds when precision is in minutes.');
		}

		if ($this->precision === Precision::Seconds && $hasNanos) {
			throw new InvalidArgumentException('Cannot step in nanoseconds when precision is in seconds.');
		}

		$this->step = $duration;

		return $this;
	}

	/**
	 * This is exclusive of the time provided.
	 */
	public function until(string $value): self
	{
		$this->until = $this->cast($value);

		return $this;
	}

	protected function cast(mixed $value): LocalTime
	{
		return $this->caster->cast($value, $this->precision);
	}

	protected function validateType(mixed $value): bool
	{
		if (!is_string($value)) {
			return false;
		}

		try {
			$time = $this->cast($value);
			return true;
		} catch (DateTimeException $e) {
			return false;
		}
	}

	protected function getConstraints(): array
	{
		return [
			'from' => $this->validateFrom(...),
			'until' => $this->validateUntil(...),
			'step' => $this->validateStep(...),
		];
	}

	private function validateFrom(mixed $value): bool
	{
		return $this->cast($value)->isAfterOrEqualTo($this->from);
	}

	private function validateUntil(mixed $value): bool
	{
		return $this->cast($value)->isBeforeOrEqualTo($this->until);
	}

	private function validateStep(mixed $value): bool
	{
		// Note: Inline nanosecond calculation here avoids float coercion issues
		// from intermediate method calls in large int multiplications.

		$input = $this->cast($value)->atDate(LocalDate::now(TimeZone::utc()))->atTimeZone(TimeZone::utc())->getInstant();
		$from = $this->from->atDate(LocalDate::now(TimeZone::utc()))->atTimeZone(TimeZone::utc())->getInstant();

		$inputNanos = BigInteger::of($input->getEpochSecond())
			->multipliedBy(BigInteger::of(1_000_000_000))
			->plus(BigInteger::of($input->getNano()));
		$fromNanos = BigInteger::of($from->getEpochSecond())
			->multipliedBy(BigInteger::of(1_000_000_000))
			->plus(BigInteger::of($from->getNano()));
		$stepNanos = BigInteger::of($this->step->getSeconds())
			->multipliedBy(BigInteger::of(1_000_000_000))
			->plus(BigInteger::of($this->step->getNanos()));

		if ($stepNanos->isZero()) {
			return false;
		}

		return $inputNanos->minus($fromNanos)->remainder($stepNanos)->isZero();
	}

	/**
	 * @return SerializedTime
	 */
	public function serialize(): object
	{
		return (object)[
			'type' => $this->type->value,
			'name' => $this->name->value,
			'optional' => $this->optional,
			'value' => $this->defaultValue->unwrap() !== null ? $this->cast($this->defaultValue->unwrap())->__toString() : null,
			'fields' => [],
			'from' => $this->from->__toString(),
			'until' => $this->until->__toString(),
			'step' => $this->step->__toString(),
			'precision_unit' => $this->precision->value,
			'precision_mode' => self::getPrecisionModeFromCaster($this->caster),
		];
	}

	/**
	 * @param SerializedTime $serialized
	 */
	public static function deserialize(object $serialized, Field\Factory $fieldFactory): static
	{
		if ($serialized->type !== 'time') {
			throw new InvalidArgumentException('Invalid serialized type for Time field.');
		}

		$instance = new self(
			new Property\Name($serialized->name),
			Precision::from($serialized->precision_unit),
			self::getCasterFromPrecisionMode($serialized->precision_mode)
		);

		$instance->optional = $serialized->optional;

		return $instance
			->from($serialized->from)
			->until($serialized->until)
			->inIncrementsOf($serialized->step)
			->prefill($serialized->value);
	}

	private static function getPrecisionModeFromCaster(PrecisionCaster $caster): string
	{
		return match (get_class($caster)) {
			TruncatePrecision::class => 'truncate',
			PreservePrecision::class => 'preserve',
			default => throw new InvalidArgumentException('Unsupported precision caster.'),
		};
	}

	private static function getCasterFromPrecisionMode(string $mode): PrecisionCaster
	{
		return match ($mode) {
			'truncate' => new TruncatePrecision(),
			'preserve' => new PreservePrecision(),
			default => throw new InvalidArgumentException('Unsupported precision mode.'),
		};
	}
}
