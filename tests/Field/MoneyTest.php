<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Brick\Math\BigDecimal;
use Meraki\Schema\Field;
use Meraki\Schema\Field\CompositeTestCase;
use Meraki\Schema\Field\Money;
use Meraki\Schema\Property\Name;
use Meraki\Schema\ValidationStatus;
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
	public function it_fails_overall_when_a_subfield_constraint_is_violated(): void
	{
		$field = $this->createSubject()->minOf('AUD', '10.00');
		$field->input(['currency' => 'AUD', 'amount' => '5.00']); // below the minimum

		$result = $field->validate();

		$this->assertSame(ValidationStatus::Failed, $result->status);
		$this->assertSame(ValidationStatus::Failed, $result->get('cost.amount')->status);
	}

	#[Test]
	public function it_accepts_subfield_values_keyed_by_local_name(): void
	{
		$field = $this->createSubject();

		$field->input(['amount' => '1500', 'currency' => 'AUD']);

		$this->assertSame('AUD', $field->currency->resolvedValue->unwrap());
		$this->assertSame('1500', $field->amount->resolvedValue->unwrap());
	}

	#[Test]
	public function it_accepts_an_object_as_the_composite_value(): void
	{
		$field = $this->createSubject();

		$field->input((object) ['amount' => '1500', 'currency' => 'AUD']);

		$this->assertSame('AUD', $field->currency->resolvedValue->unwrap());
		$this->assertSame('1500', $field->amount->resolvedValue->unwrap());
	}

	#[Test]
	public function it_accepts_local_subfield_keys_when_prefilling(): void
	{
		$field = $this->createSubject()->prefill(['amount' => '1500', 'currency' => 'AUD']);

		$this->assertSame('AUD', $field->currency->defaultValue->unwrap());
		$this->assertSame('1500', $field->amount->defaultValue->unwrap());
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
			'currency' => 'AUD',
			'amount' => $amount,
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
				'currency' => 'AUD',
				'amount' => $amount,
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
				'currency' => $currency,
				'amount' => '0.99',
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
				'currency' => $currency,
				'amount' => '1001.00',
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
				'currency' => $currency,
				'amount' => '1.23',
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
			'currency' => 'AUD',
			'amount' => '1.23',
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
				'currency' => 'AUD',
				'amount' => '1.234',
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
				'currency' => 'AUD',
				'amount' => '1.234',
			]);

		$result = $field->validate();

		$this->assertConstraintValidationResultPassedForField('cost.amount', 'cost.amount.scale', $result);
	}
}
