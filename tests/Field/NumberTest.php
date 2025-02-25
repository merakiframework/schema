<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Number;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass, DataProvider};

#[CoversClass(Number::class)]
final class NumberTest extends FieldTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$number = $this->createField();

		$this->assertInstanceOf(Number::class, $number);
	}

	public function createField(): Number
	{
		return new Number(new Attribute\Name('number'));
	}

	#[Test]
	#[DataProvider('validNumbers')]
	public function it_validates_valid_numbers(mixed $number): void
	{
		$field = $this->createField()
			->input($number);

		$this->assertTrue($field->validationResult->passed());
	}

	#[Test]
	#[DataProvider('invalidNumbers')]
	public function it_does_not_validate_invalid_numbers(mixed $number): void
	{
		$field = $this->createField()
			->input($number);

		$this->assertTrue($field->validationResult->failed());
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

	public function usesConstraints(): bool
	{
		return true;
	}

	public function getExpectedType(): string
	{
		return 'number';
	}

	public function getValidValue(): int|float
	{
		return 123;
	}

	public function getInvalidValue(): string
	{
		return 'abc';
	}

	public function createValidConstraint(): Constraint
	{
		return new Attribute\Max(125);
	}

	public function createInvalidConstraint(): Constraint
	{
		return new Attribute\Max(1);
	}
}
