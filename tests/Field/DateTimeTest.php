<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\DateTime\TimePrecision;
use Meraki\Schema\FieldTestCase;
use Meraki\Schema\Field\DateTime;
use Meraki\Schema\Property\Name;
use Meraki\Schema\ValidationStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

#[Group('field')]
#[CoversClass(DateTime::class)]
final class DateTimeTest extends FieldTestCase
{
	public function createField(): DateTime
	{
		return new DateTime(new Name('date_time'));
	}

	#[Test]
	#[DataProvider('fromConstraintExpectationsForMinutePrecision')]
	public function from_constraint_meets_expectations_for_minute_precision(string $minDateTime, string $inputDateTime, ValidationStatus $expectedStatus): void
	{
		$field = (new DateTime(new Name('date_time'), precision: TimePrecision::Minutes))
			->from($minDateTime)
			->input($inputDateTime);

		$result = $field->validate();

		$this->assertConstraintValidationResultHasStatusOf($expectedStatus, 'from', $result);
	}

	public static function fromConstraintExpectationsForMinutePrecision(): array
	{
		return [
			'input before from datetime - hour component' => ['2025-02-23T10:00:00', '2025-02-23T09:00:00', ValidationStatus::Failed],
			'input same as from datetime - hour component' => ['2025-02-23T10:00:00', '2025-02-23T10:00:00', ValidationStatus::Passed],
			'input after from datetime - hour component' => ['2025-02-23T10:00:00', '2025-02-23T11:00:00', ValidationStatus::Passed],
		];
	}

	#[Test]
	#[DataProvider('untilConstraintExpectationsForMinutePrecision')]
	public function until_constraint_meets_expectations_for_minute_precision(string $maxDateTime, string $inputDateTime, ValidationStatus $expectedStatus): void
	{
		$field = (new DateTime(new Name('date_time'), precision: TimePrecision::Minutes))
			->until($maxDateTime)
			->input($inputDateTime);

		$result = $field->validate();

		$this->assertConstraintValidationResultHasStatusOf($expectedStatus, 'until', $result);
	}

	public static function untilConstraintExpectationsForMinutePrecision(): array
	{
		return [
			'input before until datetime - hour component' => ['2025-02-23T10:00:00', '2025-02-23T09:00:00', ValidationStatus::Passed],
			'input same as until datetime - hour component' => ['2025-02-23T10:00:00', '2025-02-23T10:00:00', ValidationStatus::Failed],
			'input after until datetime - hour component' => ['2025-02-23T10:00:00', '2025-02-23T11:00:00', ValidationStatus::Failed],
		];
	}

	#[Test]
	#[DataProvider('stepConstraintExpectationsForMinutePrecision')]
	public function step_constraint_meets_expectations_for_minute_precision(string $from, string $duration, string $input, ValidationStatus $expectedStatus): void
	{
		$field = (new DateTime(new Name('date_time'), precision: TimePrecision::Minutes))
			->from($from)
			->inIncrementsOf($duration)
			->input($input);

		$result = $field->validate();

		$this->assertConstraintValidationResultHasStatusOf($expectedStatus, 'step', $result);
	}

	public static function stepConstraintExpectationsForMinutePrecision(): array
	{
		return [
			'same day' => ['2025-02-20T10:00:00', 'P1D', '2025-02-20T10:00:00', ValidationStatus::Passed],
			'1 day apart' => ['2025-02-20T10:00:00', 'P1D', '2025-02-21T10:00:00', ValidationStatus::Passed],
			'right amount of days' => ['2025-02-20T10:00:00', 'P7D', '2025-02-27T10:00:00', ValidationStatus::Passed],
			'same day but no interval (zero step)' => ['2025-02-20T10:00:00', 'PT0S', '2025-02-20T10:00:00', ValidationStatus::Failed],
			'less than right amount of days' => ['2025-02-20T10:00:00', 'P7D', '2025-02-26T10:00:00', ValidationStatus::Failed],
			'more than right amount of days' => ['2025-02-20T10:00:00', 'P7D', '2025-03-01T10:00:00', ValidationStatus::Failed],
		];
	}

	#[Test]
	#[DataProvider('fromConstraintExpectationsForSecondPrecision')]
	public function from_constraint_meets_expectations_for_second_precision(string $minDateTime, string $inputDateTime, ValidationStatus $expectedStatus): void
	{
		$field = (new DateTime(new Name('date_time'), precision: TimePrecision::Seconds))
			->from($minDateTime)
			->input($inputDateTime);

		$result = $field->validate();

		$this->assertConstraintValidationResultHasStatusOf($expectedStatus, 'from', $result);
	}

	public static function fromConstraintExpectationsForSecondPrecision(): array
	{
		return [
			'one second before' => ['2025-02-23T10:00:00', '2025-02-23T09:59:59', ValidationStatus::Failed],
			'exact match' => ['2025-02-23T10:00:00', '2025-02-23T10:00:00', ValidationStatus::Passed],
			'one second after' => ['2025-02-23T10:00:00', '2025-02-23T10:00:01', ValidationStatus::Passed],
		];
	}

	#[Test]
	#[DataProvider('stepConstraintExpectationsForNanosecondPrecision')]
	public function step_constraint_meets_expectations_for_nanosecond_precision(string $from, string $duration, string $input, ValidationStatus $expectedStatus): void
	{
		$field = (new DateTime(new Name('date_time'), precision: TimePrecision::Nanoseconds))
			->from($from)
			->inIncrementsOf($duration)
			->input($input);

		$result = $field->validate();

		$this->assertConstraintValidationResultHasStatusOf($expectedStatus, 'step', $result);
	}

	public static function stepConstraintExpectationsForNanosecondPrecision(): array
	{
		return [
			'exact nanosecond match' => ['2025-02-20T10:00:00.000000000', 'PT1S', '2025-02-20T10:00:01.000000000', ValidationStatus::Passed],
			'second off by a fraction of a nanosecond' => ['2025-02-20T10:00:00.000000000', 'PT1S', '2025-02-20T10:00:01.000000001', ValidationStatus::Failed],
			'nanoseconds are correct multiples of steps' => ['2025-02-20T10:00:00.000000000', 'PT0.000000500S', '2025-02-20T10:00:00.000001000', ValidationStatus::Passed],
			'nanoseconds are not multiples of step' => ['2025-02-20T10:00:00.000000000', 'PT0.000000250S', '2025-02-20T10:00:00.000000333', ValidationStatus::Failed],
		];
	}


	#[Test]
	#[DataProvider('invalidDateTimes')]
	public function it_does_not_validate_invalid_dates(string $date): void
	{
		$field = $this->createField()
			->input($date);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailed('type', $result);
	}

	public static function invalidDateTimes(): array
	{
		return [
			'nothing given' => [''],
			'date only' => ['2025-02-23'],
			'time only' => ['12:34:56'],
			'wrong format' => ['23-02-2025 12:34:56'],
			'single digit month' => ['2025-2-23T12:34:56'],
			'single digit day' => ['2025-02-3T12:34:56'],
			'invalid month' => ['2025-13-23T12:34:56'],
			'invalid day' => ['2025-02-32T12:34:56'],
			'invalid leap year' => ['2025-02-29T12:34:56'],
			'year too short' => ['25-02-23T12:34:56'],
			'invalid hour' => ['2025-02-23T25:34:56'],
			'invalid minute' => ['2025-02-23T12:61:56'],
			'invalid second' => ['2025-02-23T12:34:61'],
			'hour too short' => ['2025-02-23T2:34:56'],
			'minute too short' => ['2025-02-23T12:4:56'],
			'second too short' => ['2025-02-23T12:34:6'],
			'invalid timezone' => ['2025-02-23T12:34:56+25:00'],
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
