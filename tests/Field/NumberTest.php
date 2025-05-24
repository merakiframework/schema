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

	public static function validNumbers(): array
	{
		return [
			'(string) positive integer' => ['123'],
			'(string) negative integer' => ['-123'],
			'(string) integer with leading 0' => ['0123'],
			'(string) integer with leading - sign and leading 0' => ['-0123'],
			'(string) zero integer' => ['0'],
			'(string) positive zero' => ['+0'],
			'(string) negative zero' => ['-0'],

			'(string) positive float' => ['123.456'],
			'(string) negative float' => ['-123.456'],
			'(string) zero float' => ['0.00'],
			'(string) positive float with leading 0' => ['0.456'],
			'(string) negative float with leading 0' => ['-0.456'],
			'(string) float without leading digit' => ['.456'],
			'(string) scientific notation with negative exponent' => ['1.23e-3'],
			'(string) scientific notation with positive exponent' => ['1.23e+3'],
			'(string) scientific notation with exponent' => ['1.23e3'],

			'(integer) positive integer' => [123],
			'(integer) negative integer' => [-123],
			'(integer) zero integer' => [0],

			'(float) positive' => [123.456],
			'(float) negative' => [-123.456],
			'(float) zero' => [0.00],
			'(float) positive with leading 0' => [0.456],
			'(float) negative with leading 0' => [-0.456],
			'(float) no integral part' => [.456],
			'(float) without fractional part' => [123.],
			'(float) with exponent' => [1.23e3],
		];
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
			'array' => [['199.99']],
		];
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

	public static function validMinNumbers(): array
	{
		return [
			'positive integer' => ['1', '1'],
			'negative integer' => ['-1', '-1'],
			'positive and negative integer' => ['-1', '1'],
			'positive float' => ['1.0', '1.0'],
			'negative float' => ['-1.0', '-1.0'],
			'positive and negative float' => ['-1.0', '1.0'],
		];
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

	public static function invalidMinNumbers(): array
	{
		return [
			'positive integer' => ['1', '0'],
			'negative integer' => ['-1', '-2'],
			'positive float' => ['1.0', '0.9'],
			'negative float' => ['-1.0', '-2.0'],
		];
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

	public static function validMaxNumbers(): array
	{
		return [
			'positive integer' => ['1', '1'],
			'negative integer' => ['-1', '-1'],
			'positive and negative integer' => ['1', '-1'],
			'positive float' => ['1.0', '1.0'],
			'negative float' => ['-1.0', '-1.0'],
			'positive and negative float' => ['1.0', '-1.0'],
		];
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

	public static function invalidMaxNumbers(): array
	{
		return [
			'positive integer' => ['1', '2'],
			'negative integer' => ['-1', '0'],
			'positive float' => ['1.0', '1.1'],
			'negative float' => ['-1.0', '-0.9'],
		];
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

	public static function validIncrements(): array
	{
		return [
			'value is same as min' => ['1', '1', '1'],
			'whole positive integers' => ['0', '1', '7'],
			'whole negative integers' => ['-10', '5', '5'],
			'float positive integers' => ['0', '0.1', '7.5'],
			'float negative integers' => ['-10.0', '0.1', '-7.5'],
			'mixed int min and float step' => ['0', '2.5', '7.5'],
			'mixed float min and int step' => ['2.5', '5', '7.5'],
			'small step value' => ['0', '0.0001', '0.0003'],
			'negative min, small step' => ['-1.0', '0.0001', '-0.9997'],
			'zero min, large step' => ['0', '1000', '3000'],
			'negative min and positive large step' => ['-100', '50', '50'],
			'very large step' => ['0', '1e9', '2e9'],
			'really small step' => ['0', '0.00392156863', '0.00784313726'],
		];
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

	public static function invalidIncrements(): array
	{
		return [
			'whole positive integers' => ['0', '1', '7.5'],
			'whole negative integers' => ['-10', '1', '-7.5'],
			'float positive integers' => ['0', '0.5', '7.2'],
			'float negative integers' => ['-10', '0.5', '-7.2'],
			'mixed int min and float step' => ['0', '2.5', '6.2'],
			'mixed float min and int step' => ['2.5', '5', '6.2'],
			'small step value' => ['0', '0.0001', '0.00031'], // Off by a tiny bit
			'negative min, small step' => ['-1.0', '0.0001', '-0.99965'], // Off slightly
			'zero min, large step' => ['0', '1000', '2500'],
			'negative min and positive large step' => ['-100', '50', '-75'],
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
