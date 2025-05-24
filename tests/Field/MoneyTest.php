<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Field\CompositeTestCase;
use Meraki\Schema\Field\Money;
use Meraki\Schema\Property\Name;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[Group('field')]
#[CoversClass(Money::class)]
final class MoneyTest extends CompositeTestCase
{
	public function createSubject(): Money
	{
		return new Money(new Name('cost'), [
			'AUD'=> 2,
			'USD' => 2,
		]);
	}

	public function createField(): Money
	{
		return new Money(new Name('cost'), [
			'AUD' => 2,
			'USD' => 2,
		]);
	}

	#[Test]
	public function subfields_are_created(): void
	{
		$field = $this->createSubject();

		$this->assertInstanceOf(Field\Enum::class, $field->currency);
		$this->assertInstanceOf(Field\Number::class, $field->amount);
	}

	#[Test]
	public function subfields_have_correct_names(): void
	{
		$field = $this->createSubject();

		$this->assertEquals('cost.currency', (string) $field->currency->name);
		$this->assertEquals('cost.amount', (string) $field->amount->name);
	}

	#[Test]
	public function correct_value_is_set(): void
	{
		$field = $this->createSubject();

		$this->assertEquals(['cost.currency' => null, 'cost.amount' => null], $field->value->unwrap());
		$this->assertEquals(null, $field->currency->value->unwrap());
		$this->assertEquals(null, $field->amount->value->unwrap());
	}

	#[Test]
	#[DataProvider('validAmounts')]
	public function it_validates_valid_amounts(mixed $amount): void
	{
		$field = $this->createSubject()->input([
			'cost.currency' => 'AUD',
			'cost.amount' => $amount,
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultPassedForField('cost.amount', 'type', $result);
	}

	public static function validAmounts(): array
	{
		return [
			'zero integer' => ['0'],
			'zero decimal' => ['0.00'],
			'positive integer' => ['199'],
			'positive decimal' => ['199.99'],
		];
	}

	#[Test]
	#[DataProvider('invalidAmounts')]
	public function it_does_not_validate_invalid_amounts(mixed $amount): void
	{
		$field = $this->createSubject()
			->input([
				'cost.currency' => 'AUD',
				'cost.amount' => $amount,
			]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('cost.amount', 'type', $result);
	}

	public static function invalidAmounts(): array
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

	#[Test]
	public function it_has_no_value_by_default(): void
	{
		$field = $this->createSubject();

		$this->assertEquals(['cost.currency' => null, 'cost.amount' => null], $field->value->unwrap());
		$this->assertEquals(null, $field->currency->value->unwrap());
		$this->assertEquals(null, $field->amount->value->unwrap());
	}

	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$field = $this->createSubject();

		$this->assertEquals(['cost.currency' => null, 'cost.amount' => null], $field->defaultValue->unwrap());
		$this->assertEquals(null, $field->currency->defaultValue->unwrap());
		$this->assertEquals(null, $field->amount->defaultValue->unwrap());
	}

	#[Test]
	public function it_fails_if_scale_is_not_valid_for_currency(): void
	{
		$field = $this->createSubject()
			->allow('AUD', 2) // Allow AUD with scale of 2
			->input([
				'cost.currency' => 'AUD',
				'cost.amount' => '1.234',
			]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('cost.amount', 'cost.amount.scale', $result);
	}

	#[Test]
	public function it_passes_if_scale_is_valid_for_currency(): void
	{
		$field = $this->createSubject()
			->allow('AUD', 3) // Allow AUD with scale of 2
			->input([
				'cost.currency' => 'AUD',
				'cost.amount' => '1.234',
			]);

		$result = $field->validate();

		$this->assertConstraintValidationResultPassedForField('cost.amount', 'cost.amount.scale', $result);
	}
}
