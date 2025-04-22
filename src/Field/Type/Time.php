<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Type;

use Meraki\Schema\Field\Type;
use Meraki\Schema\Validator\CheckType;

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
final class Time implements Type
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

	public string $name = 'time';

	public function accepts(mixed $value): bool
	{
		return is_string($value) && preg_match(self::PATTERN, $value) === 1;
	}

	public function getValidator(): CheckType
	{
		return new CheckType($this);
	}
}


// class Time extends Field
// {
// 	public function __construct(Attribute\Name $name, Attribute ...$attributes)
// 	{

// 		$this->registerConstraints([
// 			Attribute\Min::class => $this->getValidatorForMin(),
// 			Attribute\Max::class => $this->getValidatorForMax(),
// 			Attribute\Step::class => $this->getValidatorForStep(),
// 		]);
// 	}

// 	public function from(string $value): self
// 	{
// 		$this->attributes = $this->attributes->add(new Attribute\Min($value));

// 		return $this;
// 	}

// 	public function until(string $value): self
// 	{
// 		$this->attributes = $this->attributes->add(new Attribute\Max($value));

// 		return $this;
// 	}

// 	public function inIncrementsOf(string $value): self
// 	{
// 		$this->attributes = $this->attributes->add(new Attribute\Step($value));

// 		return $this;
// 	}

// 	public static function getSupportedAttributes(): array
// 	{
// 		return [
// 			Attribute\Min::class,
// 			Attribute\Max::class,
// 			Attribute\Step::class,
// 		];
// 	}

// 	private function getValidatorForMin(): Validator
// 	{
// 		return new class implements Validator {
// 			public function validate(Attribute&Constraint $constraint, Field $field): bool
// 			{
// 				$expectedValue = LocalTime::parse($constraint->value);
// 				$actualValue = LocalTime::parse($field->value);

// 				return $actualValue->isAfterOrEqualTo($expectedValue);
// 			}
// 		};
// 	}

// 	private function getValidatorForMax(): Validator
// 	{
// 		return new class implements Validator {
// 			public function validate(Attribute&Constraint $constraint, Field $field): bool
// 			{
// 				$expectedValue = LocalTime::parse($constraint->value);
// 				$actualValue = LocalTime::parse($field->value);

// 				return $actualValue->isBeforeOrEqualTo($expectedValue);
// 			}
// 		};
// 	}

// 	private function getValidatorForStep(): Validator
// 	{
// 		return new class implements Validator {
// 			public function validate(Attribute&Constraint $constraint, Field $field): bool
// 			{
// 				$min = $field->attributes->findByName(Attribute\Min::class);

// 				// the step constraint must be relative to a minimum date
// 				// so check for min constraint
// 				if ($min === null) {
// 					return false;
// 				}

// 				$min = LocalTime::parse($min->value)->withNano(0);
// 				$step = Duration::parse($constraint->value);
// 				$value = LocalTime::parse($field->value)->withNano(0);
// 				$duration = Duration::between(
// 					$min->atDate(LocalDate::now(TimeZone::utc()))->atTimeZone(TimeZone::utc())->getInstant(),
// 					$value->atDate(LocalDate::now(TimeZone::utc()))->atTimeZone(TimeZone::utc())->getInstant()
// 				);

// 				return $duration->isEqualTo($step);
// 			}
// 		};
// 	}
// }
