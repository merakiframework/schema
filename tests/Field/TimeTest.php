<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Time\Precision;
use Meraki\Schema\Field\Time;
use Meraki\Schema\Property\Name;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[Group('field')]
#[CoversClass(Time::class)]
final class TimeTest extends FieldTestCase
{
	public function createField(): Time
	{
		return new Time(new Name('time'));
	}

	#[Test]
	#[DataProvider('validTimes')]
	public function it_validates_valid_times(string $time): void
	{
		$type = $this->createField()->input($time);

		$result = $type->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
	}

	#[Test]
	#[DataProvider('invalidTimes')]
	public function it_does_not_validate_invalid_times(string $time): void
	{
		$type = $this->createField()->input($time);

		$result = $type->validate();

		$this->assertConstraintValidationResultFailed('type', $result);
	}

	#[Test]
	public function from_constraint_passes_when_met(): void
	{
		$type = $this->createField()
			->from('10:00:00')
			->input('12:34:56');

		$result = $type->validate();

		$this->assertConstraintValidationResultPassed('from', $result);
	}

	#[Test]
	public function from_constraint_fails_when_not_met(): void
	{
		$type = $this->createField()
			->from('13:00:00')
			->input('12:34:56');

		$result = $type->validate();

		$this->assertConstraintValidationResultFailed('from', $result);
	}

	#[Test]
	public function until_constraint_passes_when_met(): void
	{
		$type = $this->createField()
			->until('13:00:00')
			->input('12:34:56');

		$result = $type->validate();

		$this->assertConstraintValidationResultPassed('until', $result);
	}

	#[Test]
	public function until_constraint_fails_when_not_met(): void
	{
		$type = $this->createField()
			->until('12:00:00')
			->input('12:34:56');

		$result = $type->validate();

		$this->assertConstraintValidationResultFailed('until', $result);
	}

	#[Test]
	#[DataProvider('validIncrements')]
	public function step_constraint_passes_when_met(Precision $precision, string $min, string $duration, string $value): void
	{
		$type = (new Time(new Name('time'), precision: $precision))
			->from($min)
			->inIncrementsOf($duration)
			->input($value);

		$result = $type->validate();

		$this->assertConstraintValidationResultPassed('step', $result);
	}

	#[Test]
	#[DataProvider('invalidIncrements')]
	public function step_constraint_fails_when_not_met(Precision $precision, string $min, string $duration, string $value): void
	{
		$type = (new Time(new Name('time'), precision: $precision))
			->from($min)
			->inIncrementsOf($duration)
			->input($value);

		$result = $type->validate();

		$this->assertConstraintValidationResultFailed('step', $result);
	}

	public static function validIncrements(): array
	{
		return [
			'same as from (seconds)' => [Precision::Seconds, '12:34:56', 'PT1S', '12:34:56'],
			'1 second' => [Precision::Seconds, '12:34:56', 'PT1S', '12:34:57'],
			'5 seconds' => [Precision::Seconds, '12:34:56', 'PT5S', '12:35:01'],
			'60 seconds' => [Precision::Seconds, '12:34:56', 'PT1M', '12:35:56'],
			'1 minute' => [Precision::Minutes, '12:35:56', 'PT1M', '12:36:56'],
			'60 minutes' => [Precision::Minutes, '12:34:56', 'PT1H', '13:34:56'],
			'1 hour' => [Precision::Nanoseconds, '13:34:56', 'PT1H', '14:34:56'],
		];
	}

	public static function invalidIncrements(): array
	{
		return [
			'seconds not increased in multiples of minute' => [Precision::Seconds, '12:35:30', 'PT1M', '12:35:32'],
			'minutes not increased in multiples of hour' => [Precision::Minutes, '13:34:56', 'PT1H', '13:40:56'],
		];
	}

	public static function validTimes(): array
	{
		return [
			'midnight/start of day' => ['00:00:00'],
			'1 second before midnight' => ['23:59:59'],
			'1 second after midnight' => ['00:00:01'],
			'only has hours and minutes' => ['11:23'],
		];
	}

	public static function invalidTimes(): array
	{
		return [
			'empty string' => [''],
			'exactly 24 hours' => ['24:00:00'],
			'more than 24 hours' => ['24:53:01'],
			'exactly 60 seconds' => ['00:00:60'],
			'more than 60 seconds' => ['00:00:61'],
			'more than 60 minutes' => ['00:61:00'],
			'exactly 60 minutes' => ['00:60:00'],
			'negative hour' => ['-01:00:00'],
			'negative minute' => ['00:-01:00'],
			'negative second' => ['00:00:-01'],
			'negative timezone for utc' => ['00:00:00-00:00'],
			'UTC time with positive utc offset' => ['00:00:00Z+00:00'],
			'UTC time with negative utc offset' => ['00:00:00Z-00:00'],
			'UTC time with positive offset that is not utc' => ['00:00:00Z+01:00'],
			'UTC time with negative offset that is not utc' => ['00:00:00Z-01:00'],
			'UTC time with positive offset that is not utc and has timezone identifier' => ['03:33:30Z+11:00[Australia/Sydney]'],
			'UTC time with negative offset that is not utc and has timezone identifier' => ['03:33:30Z-08:00[America/Los_Angeles]'],
			'UTC time with identifier' => ['03:33:30Z[UTC/UTC]'],
			'fractional seconds without seconds' => ['00:00:.123'],
			'fractional seconds without digits' => ['12:34:56.'],
			'invalid characters in hour field' => ['2a:00:00'],
			'invalid characters in minute field' => ['00:1b:00'],
			'invalid characters in second field' => ['00:00:5c'],
			'invalid fractional seconds' => ['12:34:56.abc'],
			'invalid timezone offset' => ['12:34:56+99:99'],
			'invalid identifier with invalid format' => ['12:34:56+02:00[Invalid/Timezone@]'],
			'missing hour' => [':34:56'],
			'missing minute' => ['12::56'],
			'missing second' => ['12:34:'],
		];
	}

	#[Test]
	public function it_has_no_value_by_default(): void
	{
		$field = $this->createField();

		$this->assertNull($field->value->unwrap());
	}

	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$field = $this->createField();

		$this->assertNull($field->defaultValue->unwrap());
	}
}
