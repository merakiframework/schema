<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\DateTime\PreservePrecision;
use Meraki\Schema\Field\DateTime\TimePrecision;
use Meraki\Schema\Field\DateTime\PrecisionCaster;
use Meraki\Schema\Field\DateTime\TruncatePrecision;
use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\DateTime\TimeZone;
use Brick\DateTime\DateTimeException;
use Brick\DateTime\LocalDateTime;
use Brick\DateTime\Duration;
use InvalidArgumentException;

/**
 * @phpstan-import-type SerializedField from Field
 * @phpstan-type SerializedDateTime = SerializedField&object{
 * 	type: 'date_time',
 * 	value: string|null,
 * 	from: string,
 * 	until: string,
 * 	interval: string,
 * 	precision_unit: string,
 * 	precision_mode: string
 * }
 * @extends AtomicField<string|null, SerializedDateTime>
 */
final class DateTime extends AtomicField
{
	public LocalDateTime $from;
	public LocalDateTime $until;
	public Duration $interval;

	public function __construct(
		Property\Name $name,
		public readonly TimePrecision $precision = TimePrecision::Minutes,
		private PrecisionCaster $caster = new TruncatePrecision(),
	) {
		parent::__construct(new Property\Type('date_time', $this->validateType(...)), $name);

		$this->from = LocalDateTime::min();
		$this->until = LocalDateTime::max();
		$this->interval = match ($precision) {
			TimePrecision::Minutes => Duration::ofMinutes(1),
			TimePrecision::Seconds => Duration::ofSeconds(1),
			default => Duration::ofNanos(1),
		};
	}

	public static function withSecondPrecision(Property\Name $name): self
	{
		return new self($name, TimePrecision::Seconds);
	}

	public static function withNanosecondPrecision(Property\Name $name): self
	{
		return new self($name, TimePrecision::Nanoseconds);
	}

	public static function withMinutePrecision(Property\Name $name): self
	{
		return new self($name, TimePrecision::Minutes);
	}

	/**
	 * This is inclusive of the date-time provided.
	 */
	public function from(string $dateTime): self
	{
		$this->from = $this->cast($dateTime);

		return $this;
	}

	/**
	 * This is exclusive of the date-time provided.
	 */
	public function until(string $dateTime): self
	{
		$this->until = $this->cast($dateTime);

		return $this;
	}

	/**
	 * Constrain value to be at intervals of the provided duration (in ISO 8601 format).
	 *
	 * @throws \InvalidArgumentException when trying to step in increments not allowed by the precision
	 */
	public function inIncrementsOf(string $duration): self
	{
		$duration = Duration::parse($duration);
		$hasSeconds = $duration->toSecondsPart() !== 0;
		$hasNanos = $duration->toNanosPart() !== 0;

		if ($this->precision === TimePrecision::Minutes && ($hasSeconds || $hasNanos)) {
			throw new InvalidArgumentException('Cannot step in seconds or nanoseconds when precision is in minutes.');
		}

		if ($this->precision === TimePrecision::Seconds && $hasNanos) {
			throw new InvalidArgumentException('Cannot step in nanoseconds when precision is in seconds.');
		}

		$this->interval = $duration;

		return $this;
	}

	protected function validateType(mixed $value): bool
	{
		if (!is_string($value)) {
			return false;
		}

		try {
			$dateTime = $this->cast($value);
			return true;
		} catch (DateTimeException $e) {
			return false;
		}
	}

	protected function cast(mixed $value): LocalDateTime
	{
		return $this->caster->cast($value, $this->precision);
	}

	protected function getConstraints(): array
	{
		return [
			'from' => $this->validateFrom(...),
			'until' => $this->validateUntil(...),
			'interval' => $this->validateInterval(...),
		];
	}

	private function validateFrom(mixed $value): bool
	{
		return $this->cast($value)->isAfterOrEqualTo($this->from);
	}

	private function validateUntil(mixed $value): bool
	{
		return $this->cast($value)->isBefore($this->until);
	}

	private function validateInterval(mixed $value): bool
	{
		// Note: Inline nanosecond calculation here avoids float coercion issues
		// from intermediate method calls in large int multiplications.

		$input = $this->cast($value)->atTimeZone(TimeZone::utc())->getInstant();
		$from = $this->from->atTimeZone(TimeZone::utc())->getInstant();

		$inputNanos = BigInteger::of($input->getEpochSecond())
			->multipliedBy(BigInteger::of(1_000_000_000))
			->plus(BigInteger::of($input->getNano()));
		$fromNanos = BigInteger::of($from->getEpochSecond())
			->multipliedBy(BigInteger::of(1_000_000_000))
			->plus(BigInteger::of($from->getNano()));
		$stepNanos = BigInteger::of($this->interval->getSeconds())
			->multipliedBy(BigInteger::of(1_000_000_000))
			->plus(BigInteger::of($this->interval->getNanos()));

		// Safety check: avoid division by zero
		if ($stepNanos->isZero()) {
			return false;
		}

		return $inputNanos->minus($fromNanos)->remainder($stepNanos)->isZero();
	}

	/**
	 * @return SerializedDateTime
	 */
	public function serialize(): object
	{
		return (object)[
			'type' => $this->type->value,
			'name' => $this->name->value,
			'optional' => $this->optional,
			'value' => $this->defaultValue->unwrap(),
			'fields' => [],
			'precision_unit' => $this->precision->value,
			'precision_mode' => $this->getPrecisionMode(),
			'from' => $this->from->__toString(),
			'until' => $this->until->__toString(),
			'interval' => $this->interval->__toString(),
		];
	}

	private function getPrecisionMode(): string
	{
		return match ($this->caster::class) {
			TruncatePrecision::class => 'truncate',
			PreservePrecision::class => 'preserve',
		};
	}

	private static function getCasterFromPrecisionMode(string $precisionMode): PrecisionCaster
	{
		return match ($precisionMode) {
			'truncate' => new TruncatePrecision(),
			'preserve' => new PreservePrecision(),
			default => throw new InvalidArgumentException("Unknown precision mode: $precisionMode"),
		};
	}

	/**
	 * @param SerializedDateTime $serialized
	 */
	public static function deserialize(object $serialized): static
	{
		if ($serialized->type !== 'date_time') {
			throw new InvalidArgumentException('Invalid type for DateTime field: ' . $serialized->type);
		}

		$precision = TimePrecision::from($serialized->precision_unit);
		$caster = self::getCasterFromPrecisionMode($serialized->precision_mode);
		$field = new self(new Property\Name($serialized->name), $precision, $caster);
		$field->optional = $serialized->optional;
		$field->prefill($serialized->value);
		$field->from($serialized->from);
		$field->until($serialized->until);
		$field->inIncrementsOf($serialized->interval);

		return $field;
	}
}
