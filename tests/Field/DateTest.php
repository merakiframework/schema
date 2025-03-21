<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;


use Meraki\Schema\Field\Date;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass, DataProvider};

#[CoversClass(Date::class)]
final class DateTest extends FieldTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$boolean = $this->createField();

		$this->assertInstanceOf(Date::class, $boolean);
	}

	#[Test]
	public function min_constraint_passes_when_input_is_same_as_min_date(): void
	{
		$field = $this->createField()
			->from('2025-02-23')
			->input('2025-02-23');

		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Min::class);
	}

	#[Test]
	public function min_constraint_passes_when_input_is_past_min_date(): void
	{
		$field = $this->createField()
			->from('2025-02-23')
			->input('2025-02-24');

		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Min::class);
	}

	#[Test]
	public function min_constraint_fails_when_input_is_before_min_date(): void
	{
		$field = $this->createField()
			->from('2025-02-23')
			->input('2025-02-22');

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Min::class);
	}

	#[Test]
	public function max_constraint_passes_when_input_is_before_max_date(): void
	{
		$field = $this->createField()
			->until('2025-02-23')
			->input('2025-02-22');

		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Max::class);
	}

	#[Test]
	public function max_constraint_fails_when_input_is_the_same_as_max_date(): void
	{
		$field = $this->createField()
			->until('2025-02-23')
			->input('2025-02-23');

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Max::class);
	}

	#[Test]
	public function max_constraint_fails_when_input_is_after_max_date(): void
	{
		$field = $this->createField()
			->until('2025-02-23')
			->input('2025-02-24');

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Max::class);
	}

	#[Test]
	public function step_constraint_fails_if_no_min_constraint_set(): void
	{
		$field = $this->createField()
			->inMultiplesOf('P1D')
			->input('2025-02-23');

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Step::class);
	}

	#[Test]
	#[DataProvider('validStepValues')]
	public function step_constraint_passes_when_met(string $from, string $period, string $input): void
	{
		$field = $this->createField()
			->from($from)
			->inMultiplesOf($period)
			->input($input);

		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Step::class);
	}

	#[Test]
	#[DataProvider('invalidStepValues')]
	public function step_constraint_fails_when_not_met(string $from, string $period, string $input): void
	{
		$field = $this->createField()
			->from($from)
			->inMultiplesOf($period)
			->input($input);

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Step::class);
	}

	public static function validStepValues(): array
	{
		return [
			'period of 1 day' => ['2025-02-20', 'P1D', '2025-02-21'],
			'period of multiple days' => ['2025-02-20', 'P7D', '2025-02-27'],
		];
	}

	public static function invalidStepValues(): array
	{
		return [
			'same day' => ['2025-02-20', 'P1D', '2025-02-20'],
			'less than right amount of days' => ['2025-02-20', 'P7D', '2025-02-26'],
			'more than right amount of days' => ['2025-02-20', 'P7D', '2025-03-01'],
		];
	}

	#[Test]
	#[DataProvider('invalidDates')]
	public function it_does_not_validate_invalid_dates(string $date): void
	{
		$field = $this->createField()->input($date);

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Type::class);
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

	public function createField(): Date
	{
		return new Date(new Attribute\Name('date'));
	}

	public function usesConstraints(): bool
	{
		return true;
	}

	public function getExpectedType(): string
	{
		return 'date';
	}

	public function getValidValue(): string
	{
		return '2025-02-23';
	}

	public function getInvalidValue(): string
	{
		return '';
	}

	public function createValidConstraint(): Constraint
	{
		return new Attribute\Max('2025-02-24');
	}

	public function createInvalidConstraint(): Constraint
	{
		return new Attribute\Max('2025-02-22');
	}
}
