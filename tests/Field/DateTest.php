<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Date;
use Meraki\Schema\Property\Name;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('field')]
#[CoversClass(Date::class)]
final class DateTest extends FieldTestCase
{
	public function createField(): Date
	{
		return new Date(new Name('date'));
	}

	#[Test]
	#[DataProvider('invalidDates')]
	public function it_does_not_validate_invalid_dates(string $date): void
	{
		$field = $this->createField()->input($date);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailed('type', $result);
	}

	#[Test]
	public function it_validates_valid_dates(): void
	{
		$field = $this->createField()->input('2025-02-23');

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
	}

	#[Test]
	public function it_validates_dates_with_a_default_value(): void
	{
		$field = $this->createField()
			->prefill('2025-02-23')
			->input(null);

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertEquals('2025-02-23', $field->resolvedValue->unwrap());
	}

	#[Test]
	public function it_validates_dates_with_a_default_value_and_a_value(): void
	{
		$field = $this->createField()
			->prefill('2025-02-23')
			->input('2025-02-24');

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertEquals('2025-02-24', $field->resolvedValue->unwrap());
	}

	#[Test]
	public function min_constraint_passes_when_input_is_same_as_min_date(): void
	{
		$field = $this->createField()
			->from('2025-02-23')
			->input('2025-02-23');

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultPassed('min', $result);
	}

	#[Test]
	public function min_constraint_passes_when_input_is_past_min_date(): void
	{
		$field = $this->createField()
			->from('2025-02-23')
			->input('2025-02-24');

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultPassed('min', $result);
	}

	#[Test]
	public function min_constraint_fails_when_input_is_before_min_date(): void
	{
		$field = $this->createField()
			->from('2025-02-23')
			->input('2025-02-22');

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultFailed('min', $result);
	}

	#[Test]
	public function until_max_constraint_passes_when_input_is_before_max_date(): void
	{
		$field = $this->createField()
			->until('2025-02-23')
			->input('2025-02-22');

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultPassed('max', $result);
	}

	#[Test]
	public function until_max_constraint_fails_when_input_is_at_max_date(): void
	{
		$field = $this->createField()
			->until('2025-02-23')
			->input('2025-02-23');

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultFailed('max', $result);
	}

	#[Test]
	public function until_max_constraint_fails_when_input_is_after_max_date(): void
	{
		$field = $this->createField()
			->until('2025-02-23')
			->input('2025-02-24');

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultFailed('max', $result);
	}

	#[Test]
	public function to_max_constraint_passes_when_input_is_before_max_date(): void
	{
		$field = $this->createField()
			->to('2025-02-21')
			->input('2025-02-22');

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultPassed('max', $result);
	}

	#[Test]
	public function to_max_constraint_passes_when_input_is_at_max_date(): void
	{
		$field = $this->createField()
			->to('2025-02-22')
			->input('2025-02-22');

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultPassed('max', $result);
	}

	#[Test]
	public function to_max_constraint_fails_when_input_past_max_date(): void
	{
		$field = $this->createField()
			->to('2025-02-22')
			->input('2025-02-23');

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultFailed('max', $result);
	}

	#[Test]
	#[DataProvider('validIntervals')]
	public function interval_constraint_passes_when_met(string $from, string $interval, string $value): void
	{
		$field = $this->createField()
			->from($from)
			->atIntervalsOf($interval)
			->input($value);

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultPassed('interval', $result);
	}

	#[Test]
	#[DataProvider('invalidIntervals')]
	public function interval_constraint_fails_when_not_met(string $from, string $interval, string $value): void
	{
		$field = $this->createField()
			->from($from)
			->atIntervalsOf($interval)
			->input($value);

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultFailed('interval', $result);
	}

	public static function invalidDates(): array
	{
		return [
			'nothing given' => [''],
			'date and time' => ['2025-02-23T12:34:56'],
			'time' => ['12:34:56'],
			'wrong format' => ['23-02-2025'],
			'single digit month' => ['2025-2-23'],
			'single digit day' => ['2025-02-3'],
			'invalid month' => ['2025-13-23'],
			'invalid day' => ['2025-02-32'],
			'invalid leap year' => ['2025-02-29'],
			'year too short' => ['25-02-23'],
		];
	}

	public static function validIntervals(): array
	{
		return [
			'same day' => ['2025-02-20', 'P1D', '2025-02-20'],
			'the next day' => ['2025-02-20', 'P1D', '2025-02-21'],
			'in a week' => ['2025-02-20', 'P7D', '2025-02-27'],
		];
	}

	public static function invalidIntervals(): array
	{
		return [
			'less than right amount of days' => ['2025-02-20', 'P7D', '2025-02-25'],
			'more than right amount of days' => ['2025-02-20', 'P7D', '2025-03-07'],
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
