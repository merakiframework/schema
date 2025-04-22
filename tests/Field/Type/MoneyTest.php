<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Type;

use Meraki\Schema\Field\Type\Money;
use Meraki\Schema\Attribute;
use Meraki\Schema\CompositeFieldTestCase;
use Meraki\Schema\Constraint;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass, DataProvider};

#[CoversClass(Money::class)]
final class MoneyTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$money = $this->createField();

		$this->assertInstanceOf(Money::class, $money);
	}

	public function createField(): Money
	{
		return new Money(
			new Attribute\Name('cost'),
			new Attribute\OneOf(['AUD'])
		);
	}

	#[Test]
	#[DataProvider('validAmounts')]
	public function it_validates_valid_amounts(mixed $amount): void
	{
		$field = $this->createField()
			->input($amount);

		$this->assertTrue($field->validationResult->passed());
	}

	#[Test]
	#[DataProvider('invalidAmounts')]
	public function it_does_not_validate_invalid_amounts(mixed $amount): void
	{
		$field = $this->createField()
			->input($amount);

		$this->assertTrue($field->validationResult->failed());
	}

	public static function validAmounts(): array
	{
		return [
			'integer' => [199],
			'integer with positive sign' => [+199],
			'integer with negative sign' => [-199],

			'zero' => ['0'],
			'positive zero' => ['+0'],
			'negative zero' => ['-0'],
		];
	}

	public static function invalidAmounts(): array
	{
		return [
			'integer with leading 0' => ['0199'],
			'integer with leading + sign and leading 0' => ['+0199'],
			'integer with leading - sign and leading 0' => ['-0199'],

			'float' => ['199.99'],
			'float with positive sign' => ['+199.99'],
			'float with negative sign' => ['-199.99'],
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
			'float without leading digit' => ['.456'],
			'float without decimal' => ['123.'],
		];
	}

	public function usesConstraints(): bool
	{
		return false;
	}

	public function getExpectedType(): string
	{
		return 'money';
	}

	public function getValidValue(): string
	{
		return '199.99';
	}

	public function getInvalidValue(): int
	{
		return 123;
	}

	public function createValidConstraint(): Constraint
	{
		return new Attribute\Max('200.00');
	}

	public function createInvalidConstraint(): Constraint
	{
		return new Attribute\Max('199.00');
	}
}
