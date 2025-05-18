<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field\Time\PrecisionCaster;
use Meraki\Schema\Field\Modifier\TimePrecision;
use Meraki\Schema\Field\Time\TruncatePrecision;
use Meraki\Schema\Property;
use Brick\DateTime\Duration;
use Brick\DateTime\LocalDate;
use Brick\DateTime\LocalTime;
use Brick\DateTime\TimeZone;
use InvalidArgumentException;

/**
 * A `time` value as close to ISO 8601, RFC 3339/9557, and HTML standards.
 *
 * The HTML standard does not have any time formats that have exact intersections
 * with the ISO 8601 and RFC 3339/9557 standards. The ISO 8601 standard has no way
 * to represent a timezone identifier, but the RFC 3339/9557 standard does. The
 * following formats therefore more closely align with the RFC 3339/9557 standard.
 *
 * No timezone component MUST be interpreted as local time.
 *
 * Supported formats (timezone offset):
 * - `%h:%m:%s%Z:%z` (e.g. `12:34:56+11:00`)
 * - `%h:%m:%.1s%Z:%z` (e.g. `12:34:56.5+11:00`)
 * - `%h:%m:%.2s%Z:%z` (e.g. `12:34:56.53+11:00`)
 * - `%h:%m:%.3s%Z:%z` (e.g. `12:34:56.532+11:00`)
 * - `%h:%m:%s.%u%Z:%z` (e.g. `12:34:56.532600+11:00`)
 *
 * Supported formats (timezone offset with timezone identifier):
 * - `%h:%m:%s%Z:%z[Australia/Sydney]` (e.g. `12:34:56+11:00[Australia/Sydney]`)
 * - `%h:%m:%.1s%Z:%z[Australia/Sydney]` (e.g. `12:34:56.5+11:00[Australia/Sydney]`)
 * - `%h:%m:%.2s%Z:%z[Australia/Sydney]` (e.g. `12:34:56.53+11:00[Australia/Sydney]`)
 * - `%h:%m:%.3s%Z:%z[Australia/Sydney]` (e.g. `12:34:56.532+11:00[Australia/Sydney]`)
 * - `%h:%m:%s.%u%Z:%z[Australia/Sydney]` (e.g. `12:34:56.532600+11:00[Australia/Sydney]`)
 *
 * Supported formats (UTC):
 * - `%h:%m:%sZ` (e.g. `12:34:56Z`)
 * - `%h:%m:%.1sZ` (e.g. `12:34:56.5Z`)
 * - `%h:%m:%.2sZ` (e.g. `12:34:56.53Z`)
 * - `%h:%m:%.3sZ` (e.g. `12:34:56.532Z`)
 * - `%h:%m:%s.%uZ` (e.g. `12:34:56.532600Z`)
 * - `%h:%m:%s+00:00` (e.g. `12:34:56+00:00`)
 * - `%h:%m:%.1s+00:00` (e.g. `12:34:56.5+00:00`)
 * - `%h:%m:%.2s+00:00` (e.g. `12:34:56.53+00:00`)
 * - `%h:%m:%.3s+00:00` (e.g. `12:34:56.532+00:00`)
 * - `%h:%m:%s.%u+00:00` (e.g. `12:34:56.532600+00:00`)
 */
final class Time extends AtomicField
{
	private const PATTERN = '/^
		([01]\d|2[0-3]) # Hours (00 to 23)
		:				# Separator
		([0-5]\d)		# Minutes (00 to 59)
		:				# Separator
		([0-5]\d)		# Seconds (00 to 59)
		(\.\d+)?		# Optional fractional seconds
		(?:				# Start of timezone component (optional)
			Z										# UTC indicator
			|										# OR
			(?!-00:00)								# Explicitly disallow negative UTC offset
			([+-](0[0-9]|1[0-4]):(?:00|15|30|45)) 	# Timezone offset (00:00 to 14:45)
			(?:\[(?:[a-zA-Z_]+\/[a-zA-Z0-9_]+)\])?	# Optional timezone identifier
		)?				# End of timezone component
	$/xi';

	public LocalTime $from;

	public LocalTime $until;

	public Duration $step;

	public function __construct(
		Property\Name $name,
		Property\Value $value = null,
		Property\Value $defaultValue = null,
		bool $optional = false,
		public readonly TimePrecision $precision = TimePrecision::Minutes,
		private PrecisionCaster $caster = new TruncatePrecision(),
	) {
		parent::__construct(new Property\Type('time'), $name, $value, $defaultValue, $optional);

		$this->from = LocalTime::min();
		$this->until = LocalTime::max();
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

		if ($this->precision === TimePrecision::Minutes && ($hasSeconds || $hasNanos)) {
			throw new InvalidArgumentException('Cannot step in seconds or nanoseconds when precision is in minutes.');
		}

		if ($this->precision === TimePrecision::Seconds && $hasNanos) {
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
		return is_string($value) && preg_match(self::PATTERN, $value) === 1;
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

		$inputNanos = $input->getEpochSecond() * 1_000_000_000 + $input->getNano();
		$fromNanos = $from->getEpochSecond() * 1_000_000_000 + $from->getNano();
		$stepNanos = $this->step->getSeconds() * 1_000_000_000 + $this->step->getNanos();

		if ($stepNanos === 0) {
			return false;
		}

		return ($inputNanos - $fromNanos) % $stepNanos === 0;
	}
}
