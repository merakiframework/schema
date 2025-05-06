<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Number;
use Meraki\Schema\Property\Name;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[Group('field')]
#[CoversClass(Number::class)]
final class NumberTest extends FieldTestCase
{
	public function createField(): Number
	{
		return new Number(new Name('number'));
	}

	#[Test]
	#[DataProvider('validNumbers')]
	public function it_validates_valid_numbers(mixed $number): void
	{
		$field = $this->createField()
			->input($number);

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
	}

	#[Test]
	#[DataProvider('invalidNumbers')]
	public function it_does_not_validate_invalid_numbers(mixed $number): void
	{
		$field = $this->createField()
			->input($number);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailed('type', $result);
	}

	#[Test]
	#[DataProvider('validMinNumbers')]
	public function it_passes_when_min_constraint_is_met(mixed $min, mixed $value): void
	{
		$field = $this->createField()
			->minOf($min)
			->input($value);

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('min', $result);
	}

	#[Test]
	#[DataProvider('invalidMinNumbers')]
	public function it_fails_when_min_constraint_is_not_met(mixed $min, mixed $value): void
	{
		$field = $this->createField()
			->minOf($min)
			->input($value);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailed('min', $result);
	}

	#[Test]
	#[DataProvider('validMaxNumbers')]
	public function it_passes_when_max_constraint_is_met(mixed $max, mixed $value): void
	{
		$field = $this->createField()
			->maxOf($max)
			->input($value);

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('max', $result);
	}

	#[Test]
	#[DataProvider('invalidMaxNumbers')]
	public function it_fails_when_max_constraint_is_not_met(mixed $max, mixed $value): void
	{
		$field = $this->createField()
			->maxOf($max)
			->input($value);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailed('max', $result);
	}

	#[Test]
	#[DataProvider('validIncrements')]
	public function it_passes_when_step_constraint_is_met(mixed $min, mixed $step, mixed $value): void
	{
		$field = $this->createField()
			->minOf($min)
			->inIncrementsOf($step)
			->input($value);

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('step', $result);
	}

	#[Test]
	#[DataProvider('invalidIncrements')]
	public function it_fails_when_step_constraint_is_not_met(mixed $min, mixed $step, mixed $value): void
	{
		$field = $this->createField()
			->minOf($min)
			->inIncrementsOf($step)
			->input($value);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailed('step', $result);
	}

	public static function validIncrements(): array
	{
		return [
			'value is same as min' => [1, 1, 1],
			'whole positive integers' => [0, 1, 7],
			'whole negative integers' => [-10, 5, 5],
			'float positive integers' => [0, 0.1, 7.5],
			'float negative integers' => [-10.0, 0.1, -7.5],
			'mixed int min and float step' => [0, 2.5, 7.5],
			'mixed float min and int step' => [2.5, 5, 7.5],
			'small step value' => [0, 0.0001, 0.0003],
			'negative min, small step' => [-1.0, 0.0001, -0.9997],
			'zero min, large step' => [0, 1000, 3000],
			'negative min and positive large step' => [-100, 50, 50],
			'very large step' => [0, 1e9, 2e9],
			'really small step' => [0, 0.00392156863, 0.00784313726],
		];
	}

	public static function invalidIncrements(): array
	{
		return [
			'whole positive integers' => [0, 1, 7.5],
			'whole negative integers' => [-10, 1, -7.5],
			'float positive integers' => [0, 0.5, 7.2],
			'float negative integers' => [-10, 0.5, -7.2],
			'mixed int min and float step' => [0, 2.5, 6.2],
			'mixed float min and int step' => [2.5, 5, 6.2],
			'small step value' => [0, 0.0001, 0.00031], // Off by a tiny bit
			'negative min, small step' => [-1.0, 0.0001, -0.99965], // Off slightly
			'zero min, large step' => [0, 1000, 2500],
			'negative min and positive large step' => [-100, 50, -75],
		];
	}

	public static function validMaxNumbers(): array
	{
		return [
			'positive integer' => [1, 1],
			'negative integer' => [-1, -1],
			'positive and negative integer' => [1, -1],
			'positive float' => [1.0, 1.0],
			'negative float' => [-1.0, -1.0],
			'positive and negative float' => [1.0, -1.0],
		];
	}

	public static function invalidMaxNumbers(): array
	{
		return [
			'positive integer' => [1, 2],
			'negative integer' => [-1, 0],
			'positive float' => [1.0, 1.1],
			'negative float' => [-1.0, -0.9],
		];
	}

	public static function validMinNumbers(): array
	{
		return [
			'positive integer' => [1, 1],
			'negative integer' => [-1, -1],
			'positive and negative integer' => [-1, 1],
			'positive float' => [1.0, 1.0],
			'negative float' => [-1.0, -1.0],
			'positive and negative float' => [-1.0, 1.0],
		];
	}

	public static function invalidMinNumbers(): array
	{
		return [
			'positive integer' => [1, 0],
			'negative integer' => [-1, -2],
			'positive float' => [1.0, 0.9],
			'negative float' => [-1.0, -2.0],
		];
	}

	public static function validNumbers(): array
	{
		return [
			'positive integer' => [123],
			'negative integer' => [-123],
			'integer with leading 0' => [123],
			'integer with leading + sign' => [123],
			'integer with leading + sign and leading 0' => [123],
			'integer with leading - sign and leading 0' => [-123],
			'positive float' => [0.456],
			'negative float' => [-0.456],
			'float without leading digit' => [0.456],
			'float without decimal' => [123.0],
			'scientific notation' => [1.23e3],
			'scientific notation with negative exponent' => [1.23e-3],
			'scientific notation with positive signs' => [1.23e+3],
			'zero' => [0],
			'positive zero' => [0],
			'negative zero' => [0],
		];
	}

	public static function invalidNumbers(): array
	{
		return [
			'only letters' => ['abc'],
			'letters and numbers' => ['abc123'],
			'letters and symbols' => ['abc!@#'],
			'letters, numbers, and symbols' => ['abc123!@#'],
			'symbols' => ['!@#'],
			'whitespace' => [' '],
			'nothing' => [''],
			'null' => [null],
			'boolean' => [true],
			'multiple decimal points' => ['1.23.4'],
			'missing number in exponent' => ['1e'],
		];
	}
}
