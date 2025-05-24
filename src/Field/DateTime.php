<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field\Modifier\TimePrecision;
use Meraki\Schema\Field\ValueCaster\TruncateDateTimePrecision;
use Meraki\Schema\Field\ValueCaster\DateTimePrecisionCaster;
use Meraki\Schema\Property;
use Brick\DateTime\TimeZone;
use Brick\DateTime\DateTimeException;
use Brick\DateTime\LocalDateTime;
use Brick\DateTime\Duration;
use InvalidArgumentException;

final class DateTime extends AtomicField
{
	public LocalDateTime $from;
	public LocalDateTime $until;
	public Duration $step;

	public function __construct(
		Property\Name $name,
		Property\Value $value = null,
		Property\Value $defaultValue = null,
		bool $optional = false,
		public readonly TimePrecision $precision = TimePrecision::Minutes,
		private DateTimePrecisionCaster $caster = new TruncateDateTimePrecision(),
	) {
		parent::__construct(new Property\Type('date_time', $this->validateType(...)), $name, $value, $defaultValue, $optional);

		$this->from = LocalDateTime::min();
		$this->until = LocalDateTime::max();
		$this->step = match ($precision) {
			TimePrecision::Minutes => Duration::ofMinutes(1),
			TimePrecision::Seconds => Duration::ofSeconds(1),
			default => Duration::ofNanos(1),
		};
	}

	public static function withSecondPrecision(
		Property\Name $name,
		Property\Value $value = null,
		Property\Value $defaultValue = null,
		bool $optional = false,
	): self {
		return new self($name, $value, $defaultValue, $optional, TimePrecision::Seconds);
	}

	public static function withNanosecondPrecision(
		Property\Name $name,
		Property\Value $value = null,
		Property\Value $defaultValue = null,
		bool $optional = false,
	): self {
		return new self($name, $value, $defaultValue, $optional, TimePrecision::Nanoseconds);
	}

	public static function withMinutePrecision(
		Property\Name $name,
		Property\Value $value = null,
		Property\Value $defaultValue = null,
		bool $optional = false,
	): self {
		return new self($name, $value, $defaultValue, $optional, TimePrecision::Minutes);
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

		$this->step = $duration;

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
			'step' => $this->validateStep(...),
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

	private function validateStep(mixed $value): bool
	{
		// Note: Inline nanosecond calculation here avoids float coercion issues
		// from intermediate method calls in large int multiplications.

		$input = $this->cast($value)->atTimeZone(TimeZone::utc())->getInstant();
		$from = $this->from->atTimeZone(TimeZone::utc())->getInstant();

		$inputNanos = $input->getEpochSecond() * 1_000_000_000 + $input->getNano();
		$fromNanos = $from->getEpochSecond() * 1_000_000_000 + $from->getNano();
		$stepNanos = $this->step->getSeconds() * 1_000_000_000 + $this->step->getNanos();

		// Safety check: avoid division by zero
		if ($stepNanos === 0) {
			return false;
		}

		return ($inputNanos - $fromNanos) % $stepNanos === 0;
	}
}
