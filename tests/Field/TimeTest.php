<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Time;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass, DataProvider};

#[CoversClass(Time::class)]
final class TimeTest extends FieldTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$time = $this->createField();

		$this->assertInstanceOf(Time::class, $time);
	}

	#[Test]
	#[DataProvider('validTimes')]
	public function it_validates_valid_times(string $time): void
	{
		$field = $this->createField()->input($time);

		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Type::class);
	}

	#[Test]
	#[DataProvider('invalidTimes')]
	public function it_does_not_validate_invalid_times(string $time): void
	{
		$field = $this->createField()->input($time);

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Type::class);
	}

	#[Test]
	public function min_constraint_passes_when_met(): void
	{
		$field = $this->createField()
			->from('10:00:00')
			->input('12:34:56');

		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Min::class);
	}

	#[Test]
	public function min_constraint_fails_when_not_met(): void
	{
		$field = $this->createField()
			->from('13:00:00')
			->input('12:34:56');

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Min::class);
	}

	#[Test]
	public function max_constraint_passes_when_met(): void
	{
		$field = $this->createField()
			->until('13:00:00')
			->input('12:34:56');

		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Max::class);
	}

	#[Test]
	public function max_constraint_fails_when_not_met(): void
	{
		$field = $this->createField()
			->until('12:00:00')
			->input('12:34:56');

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Max::class);
	}

	#[Test]
	public function step_constraint_fails_if_no_min_constraint_set(): void
	{
		$field = $this->createField()
			->inIncrementsOf('PT1H')
			->input('12:34:56');

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Step::class);
	}

	#[Test]
	#[DataProvider('validIncrements')]
	public function step_constraint_passes_when_met(string $min, string $duration, string $value): void
	{
		$field = $this->createField()
			->from($min)
			->inIncrementsOf($duration)
			->input($value);

		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Step::class);
	}

	public static function validIncrements(): array
	{
		return [
			'1 second' => ['12:34:56', 'PT1S', '12:34:57'],
			'60 seconds' => ['12:34:56', 'PT1M', '12:35:56'],
			'1 minute' => ['12:35:56', 'PT1M', '12:36:56'],
			'60 minutes' => ['12:34:56', 'PT1H', '13:34:56'],
			'1 hour' => ['13:34:56', 'PT1H', '14:34:56'],
		];
	}

	#[Test]
	#[DataProvider('invalidIncrements')]
	public function step_constraint_fails_when_not_met(string $min, string $duration, string $value): void
	{
		$field = $this->createField()
			->from($min)
			->inIncrementsOf($duration)
			->input($value);

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Step::class);
	}

	public static function invalidIncrements(): array
	{
		return [
			'seconds not increased in multiples of minute' => ['12:35:30', 'PT1M', '12:35:32'],
			'minutes not increased in multiples of hour' => ['13:34:56', 'PT1H', '13:40:56'],
		];
	}

	public function getExpectedType(): string
	{
		return 'time';
	}

	public function createField(): Time
	{
		return new Time(new Attribute\Name('time'));
	}

	public function getValidValue(): mixed
	{
		return '12:34:56';
	}

	public function getInvalidValue(): mixed
	{
		return '25:34:56';
	}

	public function createValidConstraint(): Constraint
	{
		return new Attribute\Min('10:00:00');
	}

	public function createInvalidConstraint(): Constraint
	{
		return new Attribute\Max('12:00:00');
	}

	public function usesConstraints(): bool
	{
		return true;
	}

	public static function validTimes(): array
	{
		return [
			'midnight/start of day (no tz info)' => ['00:00:00'],
			'1 second before midnight (no tz info)' => ['23:59:59'],
			'1 second after midnight (no tz info)' => ['00:00:01'],
			'1 second before midnight (with tz offset at utc)' => ['23:59:59+00:00'],
			'early morning (with tz offset)' => ['03:33:03+10:30'],
			'UTC with "Z"' => ['12:34:56Z'],
			'midnight with "Z"' => ['00:00:00Z'],
			'fractional seconds with UTC "Z"' => ['18:45:12.123456Z'],
			'fractional seconds with timezone offset' => ['12:00:00.999999+05:30'],
			'early morning with timezone identifier' => ['05:45:30+05:30[Asia/Kolkata]'],
			'fractional seconds with positive timezone offset and identifier' => ['23:59:59.123+02:00[Europe/Berlin]'],
			'fractional seconds with negative timezone offset and identifier' => ['15:30:45.678-08:00[America/Los_Angeles]'],
			'standard time with positive offset and identifier' => ['14:25:59+02:00[Africa/Cairo]'],
			'standard time with negative offset and identifier' => ['06:59:01-04:00[America/New_York]'],
			'midnight with positive offset and identifier' => ['00:00:00+03:00[Europe/Moscow]'],
			'midday with negative offset and identifier' => ['12:00:00-07:00[America/Denver]'],
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
			'only has minutes/seconds or hours/minutes (ambiguos format)' => ['00:00'],
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
			'UTC time with identifer' => ['03:33:30Z[UTC/UTC]'],
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
}
