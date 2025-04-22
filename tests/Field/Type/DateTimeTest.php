<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Type;


use Meraki\Schema\Field\Type\DateTime;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\FieldTestCase;
use Brick\DateTime\LocalDateTime;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass, DataProvider};

#[CoversClass(DateTime::class)]
final class DateTimeTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$field = $this->createField();

		$this->assertInstanceOf(DateTime::class, $field);
	}

	#[Test]
	public function min_constraint_passes_when_input_is_same_as_min_date(): void
	{
		$field = $this->createField()
			->from('2025-02-23T10:00:00')
			->input('2025-02-23T10:00:00');

		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Min::class);
	}

	#[Test]
	public function min_constraint_passes_when_input_is_past_min_date(): void
	{
		$field = $this->createField()
			->from('2025-02-23T10:00:00')
			->input('2025-02-24T09:00:00');

		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Min::class);
	}

	#[Test]
	public function min_constraint_fails_when_input_is_before_min_date(): void
	{
		$field = $this->createField()
			->from('2025-02-23T10:00:00')
			->input('2025-02-23T09:00:00');

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Min::class);
	}

	#[Test]
	public function max_constraint_passes_when_input_is_before_max_date(): void
	{
		$field = $this->createField()
			->until('2025-02-23T10:00:00')
			->input('2025-02-22T11:00:00');

		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Max::class);
	}

	#[Test]
	public function max_constraint_fails_when_input_is_the_same_as_max_date(): void
	{
		$field = $this->createField()
			->until('2025-02-23T10:00:00')
			->input('2025-02-23T10:00:00');

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Max::class);
	}

	#[Test]
	public function max_constraint_fails_when_input_is_after_max_date(): void
	{
		$field = $this->createField()
			->until('2025-02-23T10:00:00')
			->input('2025-02-24T09:00:00');

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Max::class);
	}

	#[Test]
	public function step_constraint_fails_if_no_min_constraint_set(): void
	{
		$field = $this->createField()
			->inIncrementsOf('PT1H')
			->input('2025-02-23T10:00:00');

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Step::class);
	}

	#[Test]
	#[DataProvider('validStepValues')]
	public function step_constraint_passes_when_met(string $from, string $duration, string $input): void
	{
		$field = $this->createField()
			->from($from)
			->inIncrementsOf($duration)
			->input($input);

		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Step::class);
	}

	#[Test]
	#[DataProvider('invalidStepValues')]
	public function step_constraint_fails_when_not_met(string $from, string $duration, string $input): void
	{
		$field = $this->createField()
			->from($from)
			->inIncrementsOf($duration)
			->input($input);

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Step::class);
	}

	public static function validStepValues(): array
	{
		return [
			'period of 1 day' => ['2025-02-20T10:00:00', 'P1D', '2025-02-21T10:00:00'],
			'period of multiple days' => ['2025-02-20T10:00:00', 'P7D', '2025-02-27T10:00:00'],
		];
	}

	public static function invalidStepValues(): array
	{
		return [
			'same day' => ['2025-02-20T10:00:00', 'P1D', '2025-02-20T10:00:00'],
			'less than right amount of days' => ['2025-02-20T10:00:00', 'P7D', '2025-02-26T10:00:00'],
			'more than right amount of days' => ['2025-02-20T10:00:00', 'P7D', '2025-03-01T10:00:00'],
		];
	}

	#[Test]
	#[DataProvider('invalidDateTimes')]
	public function it_does_not_validate_invalid_dates(string $date): void
	{
		$field = $this->createField()->input($date);

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Type::class);
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

	public function createField(): DateTime
	{
		return new DateTime(new Attribute\Name('when'));
	}

	public function usesConstraints(): bool
	{
		return true;
	}

	public function getExpectedType(): string
	{
		return 'date_time';
	}

	public function getValidValue(): string
	{
		return '2025-02-23T10:00:00';
	}

	public function getInvalidValue(): string
	{
		return '';
	}

	public function createValidConstraint(): Constraint
	{
		return new Attribute\Max('2025-02-23T11:00:00');
	}

	public function createInvalidConstraint(): Constraint
	{
		return new Attribute\Step('1 day');
	}
}
