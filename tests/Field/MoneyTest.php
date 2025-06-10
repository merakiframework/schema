<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Brick\Math\BigDecimal;
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
	#[DataProvider('validCurrencies')]
	public function can_require_a_minimum_amount_per_currency(string $currency): void
	{
		$field = $this->createSubject()
			->minOf('USD', '10.00')
			->minOf('AUD', '20.00')
			->input([
				'cost.currency' => $currency,
				'cost.amount' => '0.99',
			]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('cost.amount', 'cost.amount.min', $result);
	}

	#[Test]
	#[DataProvider('validCurrencies')]
	public function can_require_maximum_amount_for_per_currency(string $currency): void
	{
		$field = $this->createSubject()
			->maxOf('USD', '500.00')
			->maxOf('AUD', '1000.00')
			->input([
				'cost.currency' => $currency,
				'cost.amount' => '1001.00',
			]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('cost.amount', 'cost.amount.max', $result);
	}

	#[Test]
	#[DataProvider('validCurrencies')]
	public function can_require_a_step_for_per_currency(string $currency): void
	{
		$field = $this->createSubject()
			->minOf('USD', '0')
			->maxOf('USD', '1000')
			->inIncrementsOf('USD', '10.00')
			->minOf('AUD', '0')
			->maxOf('AUD', '1000')
			->inIncrementsOf('AUD', '10.00')
			->input([
				'cost.currency' => $currency,
				'cost.amount' => '1.23',
			]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('cost.amount', 'cost.amount.step', $result);
	}

	public static function validCurrencies(): array
	{
		return [
			'USD' => ['USD'],
			'AUD' => ['AUD'],
		];
	}

	#[Test]
	public function it_scales_amount_to_correct_decimal_places(): void
	{
		$field = new Money(new Name('cost'), [
			'AUD'=> 4,
			'USD' => 2,
		]);
		$field->input([
			'cost.currency' => 'AUD',
			'cost.amount' => '1.23',
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultPassedForField('cost.amount', 'cost.amount.scale', $result);
	}

	#[Test]
	public function it_fails_if_scale_is_not_valid_for_currency(): void
	{
		$field = $this->createSubject()
			->allow('AUD', 2)
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
			->allow('AUD', 3)
			->input([
				'cost.currency' => 'AUD',
				'cost.amount' => '1.234',
			]);

		$result = $field->validate();

		$this->assertConstraintValidationResultPassedForField('cost.amount', 'cost.amount.scale', $result);
	}

	#[Test]
	public function it_serializes_and_deserializes(): void
	{
		$moneyNormalized = [
			'cost.currency' => 'AUD',
			'cost.amount' => '100.000',
		];
		$sut = $this->createSubject()
			->allow('AUD', 3)
			->minOf('AUD', '0')
			->maxOf('AUD', '1000.000')
			->inIncrementsOf('AUD', '0.001')
			->minOf('USD', '20')
			->maxOf('USD', '500')
			->inIncrementsOf('USD', '10')
			->prefill([
				'cost.currency' => 'AUD',
				'cost.amount' => '100',
			]);

		$serialized = $sut->serialize();

		// serialized money will be normalized
		$this->assertEquals('money', $serialized->type);
		$this->assertEquals('cost', $serialized->name);
		$this->assertFalse($serialized->optional);
		$this->assertEquals(['AUD', 'USD'], $serialized->allowed);
		$this->assertEquals(['AUD' => 3, 'USD' => 2], $serialized->scale);
		$this->assertEquals(['AUD' => '0.000', 'USD' => '20.00'], $serialized->min);
		$this->assertEquals(['AUD' => '1000.000', 'USD' => '500.00'], $serialized->max);
		$this->assertEquals(['AUD' => '0.001', 'USD' => '10.00'], $serialized->step);
		$this->assertEquals($moneyNormalized, $serialized->value);

		$deserialized = Money::deserialize($serialized);

		$this->assertEquals('money', $deserialized->type->value);
		$this->assertEquals('cost', $deserialized->name->value);
		$this->assertFalse($deserialized->optional);
		$this->assertEquals(['AUD', 'USD'], $deserialized->allowed);
		$this->assertEquals(['AUD' => 3, 'USD' => 2], $deserialized->scale);
		$this->assertEquals(['AUD' => BigDecimal::of('0.000'), 'USD' => BigDecimal::of('20.00')], $deserialized->min);
		$this->assertEquals(['AUD' => BigDecimal::of('1000.000'), 'USD' => BigDecimal::of('500.00')], $deserialized->max);
		$this->assertEquals(['AUD' => BigDecimal::of('0.001'), 'USD' => BigDecimal::of('10.00')], $deserialized->step);
		$this->assertEquals($moneyNormalized, $deserialized->defaultValue->unwrap());
	}

	#[Test]
	public function children_returns_serialized_fields(): void
	{
		$field = $this->createSubject()->prefill([
			'cost.currency' => 'AUD',
			'cost.amount' => '100.00',
		]);
		$serialized = $field->serialize();
		$children = $serialized->children();

		$this->assertCount(2, $children);
		$this->assertSerializedChildrenContainsFieldWithNameOf('cost.currency', $children);
		$this->assertSerializedChildrenContainsFieldWithNameOf('cost.amount', $children);
	}
}
