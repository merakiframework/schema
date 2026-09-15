<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Money;
use Meraki\Schema\Field\Money\Value;
use Meraki\Schema\FieldName;
use Meraki\Schema\FieldTestCase;
use Brick\Math\BigDecimal;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[Group('field')]
#[CoversClass(Money::class)]
#[CoversClass(Value::class)]
final class MoneyTest extends FieldTestCase
{
	public function createField(): Money
	{
		// Just the code: AUD's own minor unit is what a settleable amount wants.
		return new Money(new FieldName('cost'), ['AUD']);
	}

	/** @return array<string, mixed> */
	private static function amount(string $currency, string $amount): array
	{
		return ['currency' => $currency, 'amount' => $amount];
	}

	// ── one field, one value ───────────────────────────────────────────────────────────────

	#[Test]
	public function it_carries_the_currency_and_the_amount_together(): void
	{
		// Inseparable: 12.50 means nothing until you know whether it is dollars or yen.
		$value = $this->createField()->resolve((object) self::amount('AUD', '12.50'))->value;

		$this->assertInstanceOf(Value::class, $value);
		$this->assertSame('AUD', $value->currency);
		$this->assertTrue($value->amount->isEqualTo(BigDecimal::of('12.50')));
	}

	#[Test]
	public function it_accepts_its_own_value_object(): void
	{
		$value = new Value('AUD', BigDecimal::of('12.50'));

		$this->assertEquals($value, $this->createField()->resolve($value)->value);
	}

	#[Test]
	public function a_currency_is_upper_cased(): void
	{
		$this->assertSame('AUD', $this->createField()->resolve((object) self::amount('aud', '1.00'))->value->currency);
	}

	#[Test]
	#[DataProvider('notMoney')]
	public function it_rejects_what_cannot_be_read_as_money(mixed $given): void
	{
		$this->assertShapeFailed($this->createField()->validate((object) $given));
	}

	/** @return array<string, array{mixed}> */
	public static function notMoney(): array
	{
		return [
			'a bare number' => [12.50],
			'a string' => ['AUD 12.50'],
			'nothing at all' => [null],
			'no currency' => [['amount' => '12.50']],
			'no amount' => [['currency' => 'AUD']],
			'an amount that is not a number' => [['currency' => 'AUD', 'amount' => 'abc']],
			'a currency that is not three letters' => [['currency' => 'AUSD', 'amount' => '1.00']],
		];
	}

	#[Test]
	public function each_constraint_names_the_half_it_is_about(): void
	{
		$expected = [
			'allowedCurrencies' => 'currency',
			'minAmount' => 'amount',
			'maxAmount' => 'amount',
			'scale' => 'amount',
		];

		foreach ($this->createField()->constraints as $constraint) {
			$this->assertSame($expected[$constraint->name], $constraint->part, $constraint->name);
		}
	}

	#[Test]
	public function a_constraint_name_carries_no_trace_of_the_field_name(): void
	{
		$field = new Money(new FieldName('shipping_cost'), ['AUD' => 2]);

		$names = implode(',', $field->validate((object) self::amount('AUD', '1.00'))->constraintNames);

		$this->assertStringNotContainsString('shipping_cost', $names);
		$this->assertStringNotContainsString('.', $names);
	}

	// ── currencies ────────────────────────────────────────────────────────────────────────

	#[Test]
	public function it_reports_a_currency_that_is_not_allowed(): void
	{
		$failed = $this->createField()->validate((object) self::amount('USD', '1.00'))->forConstraint('allowedCurrencies');

		$this->assertTrue($failed->failed());
		$this->assertSame(['AUD'], $failed->bound);
	}

	#[Test]
	public function any_currency_is_accepted_when_none_was_named(): void
	{
		$field = new Money(new FieldName('cost'));

		$this->assertConstraintValidationResultSkipped('allowedCurrencies', $field->validate((object) self::amount('XYZ', '1.00')));
		$this->assertConstraintValidationResultSkipped('scale', $field->validate((object) self::amount('XYZ', '1.00')));
	}

	#[Test]
	public function currencies_accumulate(): void
	{
		$field = $this->createField()->allowCurrencies(['JPY' => 0, 'BHD' => 3]);

		$this->assertSame(['AUD' => 2, 'JPY' => 0, 'BHD' => 3], $field->allowedCurrencies);
	}

	#[Test]
	public function a_currency_brings_its_own_scale_from_the_standard(): void
	{
		// ISO 4217 already says how many decimal places each currency has, so naming one is enough
		// and there is no table for the author to keep.
		$field = new Money(new FieldName('cost'), ['AUD', 'JPY', 'BHD', 'CLF']);

		$this->assertSame(['AUD' => 2, 'JPY' => 0, 'BHD' => 3, 'CLF' => 4], $field->allowedCurrencies);
	}

	#[Test]
	public function a_scale_may_be_overridden_for_a_unit_price(): void
	{
		// Fuel at $1.859/L is denominated in dollars and finer than a cent, because it is a rate
		// rather than an amount anyone settles. Writing the 3 out is the point: an amount finer
		// than the currency allows is a typo far more often than it is intent.
		$fuel = new Money(new FieldName('price_per_litre'), ['AUD' => 3]);

		$this->assertSame(['AUD' => 3], $fuel->allowedCurrencies);
		$this->assertConstraintValidationResultPassed('scale', $fuel->validate((object) self::amount('AUD', '1.859')));
		$this->assertConstraintValidationResultFailed('scale', $fuel->validate((object) self::amount('AUD', '1.8599')));
	}

	#[Test]
	public function the_two_shapes_mix_in_one_call(): void
	{
		$field = new Money(new FieldName('cost'), ['JPY', 'AUD' => 3]);

		$this->assertSame(['JPY' => 0, 'AUD' => 3], $field->allowedCurrencies);
	}

	#[Test]
	public function the_last_mention_of_a_currency_wins(): void
	{
		// So an override may follow a plain mention, rather than the order mattering.
		$this->assertSame(['AUD' => 4], $this->createField()->allowCurrencies(['AUD' => 4])->allowedCurrencies);
		$this->assertSame(['AUD' => 2], (new Money(new FieldName('c'), ['AUD' => 4]))->allowCurrencies(['AUD'])->allowedCurrencies);
	}

	#[Test]
	public function a_code_is_read_in_any_case_and_stored_upper_cased(): void
	{
		$this->assertSame(['AUD' => 2, 'JPY' => 1], (new Money(new FieldName('c'), [' aud ', 'jpy' => 1]))->allowedCurrencies);
	}

	#[Test]
	#[DataProvider('badCurrencyDeclarations')]
	public function a_currency_the_standard_does_not_describe_is_refused_where_it_is_written(callable $attempt): void
	{
		$this->expectException(InvalidArgumentException::class);

		$attempt();
	}

	/** @return array<string, array{callable}> */
	public static function badCurrencyDeclarations(): array
	{
		return [
			'four letters' => [fn(): Money => new Money(new FieldName('c'), ['AUSD' => 2])],
			'two letters' => [fn(): Money => new Money(new FieldName('c'), ['AU' => 2])],
			'negative decimal places' => [fn(): Money => new Money(new FieldName('c'), ['AUD' => -1])],
			// Three letters, and still not a currency. Only checkable now that the standard is
			// actually consulted rather than the shape being taken for the substance.
			'a code that is not a currency' => [fn(): Money => new Money(new FieldName('c'), ['ZZZ'])],
			'the same, keyed' => [fn(): Money => new Money(new FieldName('c'), ['ZZZ' => 2])],
			'a scale that is not a number' => [fn(): Money => new Money(new FieldName('c'), ['AUD' => '2'])],
			'a bare code that is not a string' => [fn(): Money => new Money(new FieldName('c'), [123])],
			// The numeric ISO form: 036 is AUD, and the underlying provider would resolve it. This
			// field's surface is alpha-3 throughout, and an integer key already means "a bare code".
			'the numeric ISO form' => [fn(): Money => new Money(new FieldName('c'), ['036'])],
		];
	}

	#[Test]
	public function a_submitted_currency_that_is_not_real_is_the_constraints_to_report(): void
	{
		// Not a shape failure: the author's allow-list is checked where it is written, but a
		// submitted currency is a value that happens to be wrong, and naming the right constraint
		// is what lets a form mark the right input.
		$resolved = $this->createField()->validate((object) self::amount('ZZZ', '1.00'));

		$this->assertShapePassed($resolved);
		$this->assertConstraintValidationResultFailed('allowedCurrencies', $resolved);
	}

	// ── scale is per currency ─────────────────────────────────────────────────────────────

	#[Test]
	public function an_amount_must_fit_the_currencys_minor_unit(): void
	{
		// No half-cents.
		$this->assertConstraintValidationResultFailed('scale', $this->createField()->validate((object) self::amount('AUD', '12.505')));
		$this->assertConstraintValidationResultPassed('scale', $this->createField()->validate((object) self::amount('AUD', '12.50')));
	}

	#[Test]
	public function a_currency_with_no_minor_unit_takes_no_decimals(): void
	{
		// JPY has none, which is the whole reason scale is per currency at all.
		$field = new Money(new FieldName('cost'), ['JPY']);

		$this->assertConstraintValidationResultFailed('scale', $field->validate((object) self::amount('JPY', '700.5')));
		$this->assertConstraintValidationResultPassed('scale', $field->validate((object) self::amount('JPY', '700')));
	}

	#[Test]
	public function trailing_zeros_are_not_decimal_places_the_currency_would_lose(): void
	{
		$this->assertConstraintValidationResultPassed('scale', $this->createField()->validate((object) self::amount('AUD', '12.5000')));
	}

	// ── the same money, written two ways ──────────────────────────────────────────────────

	#[Test]
	public function two_amounts_are_the_same_money_whatever_scale_they_were_written_at(): void
	{
		// BigDecimal keeps the scale it was given, so `==` calls these different. They are not:
		// `12.50` and `12.5` are the same money, and only the value object knows that.
		$written = new Value('AUD', BigDecimal::of('12.50'));
		$shorter = new Value('AUD', BigDecimal::of('12.5'));

		$this->assertFalse($written == $shorter, 'structural comparison is the wrong answer here');
		$this->assertTrue($written->equals($shorter));
	}

	#[Test]
	public function the_currency_still_has_to_match(): void
	{
		$aud = new Value('AUD', BigDecimal::of('12.50'));
		$usd = new Value('USD', BigDecimal::of('12.50'));

		$this->assertFalse($aud->equals($usd));
	}

	#[Test]
	public function the_scale_a_consumer_reads_back_is_the_one_submitted(): void
	{
		// Equality ignores the scale; storage does not. `12.50` is how it was written, and a
		// consumer displaying money usually wants exactly that.
		$resolved = $this->createField()->validate((object) self::amount('AUD', '12.50'));

		$this->assertSame('12.50', (string) $resolved->value->amount);
	}

	// ── the bound a failure reports ───────────────────────────────────────────────────────

	#[Test]
	public function a_failure_reports_the_bound_that_actually_applied(): void
	{
		// The minimum is held per currency, so "the minimum" has no single answer until an amount
		// arrives naming one. Without this a message could say a value was too small but not what
		// it should have reached.
		$field = (new Money(new FieldName('cost'), ['AUD', 'USD']))
			->minAmountOf('AUD', '10.00')
			->minAmountOf('USD', '7.00');

		$this->assertSame('10.00', $field->validate((object) self::amount('AUD', '5.00'))->forConstraint('minAmount')->bound);
		$this->assertSame('7.00', $field->validate((object) self::amount('USD', '5.00'))->forConstraint('minAmount')->bound);
	}

	#[Test]
	public function the_declared_bound_stays_what_is_knowable_without_a_value(): void
	{
		// Read off the definition rather than a result, so it can only say something when a single
		// currency makes the answer unambiguous.
		$several = (new Money(new FieldName('cost'), ['AUD', 'USD']))->minAmountOf('AUD', '10.00');
		$one = (new Money(new FieldName('cost'), ['AUD']))->minAmountOf('AUD', '10.00');

		$this->assertNull($several->constraints->named('minAmount')->bound);
		$this->assertSame('10.00', $one->constraints->named('minAmount')->bound);
	}

	#[Test]
	public function the_scale_reported_is_the_submitted_currencys_own(): void
	{
		$field = new Money(new FieldName('cost'), ['AUD', 'JPY']);

		$this->assertSame(2, $field->validate((object) self::amount('AUD', '1.005'))->forConstraint('scale')->bound);
		$this->assertSame(0, $field->validate((object) self::amount('JPY', '1.5'))->forConstraint('scale')->bound);
	}

	#[Test]
	public function a_value_that_could_not_be_read_invents_no_bound(): void
	{
		// There is no currency to look one up by, and making one up would be worse than none.
		$field = (new Money(new FieldName('cost'), ['AUD', 'USD']))->minAmountOf('AUD', '10.00');

		$resolved = $field->validate('rubbish');

		$this->assertTrue($resolved->shape->failed());
		$this->assertNull($resolved->forConstraint('minAmount')->bound);
	}

	// ── bounds are per currency ───────────────────────────────────────────────────────────

	#[Test]
	public function it_reports_an_amount_below_the_minimum(): void
	{
		$failed = $this->createField()->minAmountOf('AUD', '7.00')
			->validate((object) self::amount('AUD', '5.00'))
			->forConstraint('minAmount');

		$this->assertTrue($failed->failed());
		$this->assertSame('7.00', (string) $failed->bound);
	}

	#[Test]
	public function it_reports_an_amount_above_the_maximum(): void
	{
		$this->assertConstraintValidationResultFailed(
			'maxAmount',
			$this->createField()->maxAmountOf('AUD', '7.00')->validate((object) self::amount('AUD', '9.00')),
		);
	}

	#[Test]
	public function a_bound_applies_only_to_its_own_currency(): void
	{
		// Seven dollars and seven hundred yen are different requirements, and there is no exchange
		// rate here — so which applies depends on what was submitted.
		$field = (new Money(new FieldName('cost'), ['AUD' => 2, 'JPY' => 0]))
			->minAmountOf('AUD', '7.00')
			->minAmountOf('JPY', '700');

		$this->assertConstraintValidationResultFailed('minAmount', $field->validate((object) self::amount('AUD', '5.00')));
		$this->assertConstraintValidationResultPassed('minAmount', $field->validate((object) self::amount('AUD', '9.00')));
		$this->assertConstraintValidationResultFailed('minAmount', $field->validate((object) self::amount('JPY', '500')));
		$this->assertConstraintValidationResultPassed('minAmount', $field->validate((object) self::amount('JPY', '900')));
	}

	#[Test]
	public function a_currency_with_no_bound_of_its_own_is_not_bounded(): void
	{
		$field = (new Money(new FieldName('cost'), ['AUD' => 2, 'JPY' => 0]))->minAmountOf('AUD', '7.00');

		$this->assertConstraintValidationResultSkipped('minAmount', $field->validate((object) self::amount('JPY', '1')));
	}

	#[Test]
	public function a_bound_only_has_something_to_interpolate_when_one_currency_is_allowed(): void
	{
		// With several it depends on which the submitted amount turns out to be, so there is no one
		// number a message could name.
		$one = $this->createField()->minAmountOf('AUD', '7.00');
		$several = $one->allowCurrencies(['JPY' => 0]);

		$this->assertSame('7.00', (string) $one->constraints->named('minAmount')->bound);
		$this->assertNull($several->constraints->named('minAmount')->bound);
	}

	#[Test]
	#[DataProvider('impossibleBounds')]
	public function an_impossible_bound_is_refused_where_it_is_written(callable $attempt): void
	{
		$this->expectException(InvalidArgumentException::class);

		$attempt($this->createField());
	}

	/** @return array<string, array{callable}> */
	public static function impossibleBounds(): array
	{
		return [
			'a currency that is not allowed' => [fn(Money $m): Money => $m->minAmountOf('GBP', '1.00')],
			'more decimals than the currency has' => [fn(Money $m): Money => $m->minAmountOf('AUD', '1.005')],
			'not a number' => [fn(Money $m): Money => $m->minAmountOf('AUD', 'abc')],
			'a minimum above the maximum' => [fn(Money $m): Money => $m->maxAmountOf('AUD', '5.00')->minAmountOf('AUD', '9.00')],
			'a maximum below the minimum' => [fn(Money $m): Money => $m->minAmountOf('AUD', '9.00')->maxAmountOf('AUD', '5.00')],
		];
	}

	#[Test]
	public function clearing_the_currencies_drops_the_bounds_that_depended_on_them(): void
	{
		$field = $this->createField()->minAmountOf('AUD', '7.00')->clearAllowedCurrencies();

		$this->assertSame([], $field->allowedCurrencies);
		$this->assertSame([], $field->minAmounts);
	}

	// ── the value object ──────────────────────────────────────────────────────────────────

	#[Test]
	public function it_is_an_internal_representation_rather_than_something_to_print(): void
	{
		// No __toString(): where the symbol sits and which separators are used is a locale's
		// business, and guessing at it here would be wrong in most of the world.
		$value = new Value('AUD', BigDecimal::of('12.50'));

		$this->assertNotInstanceOf(\Stringable::class, $value);
		$this->assertFalse(method_exists($value, '__toString'));
		$this->assertSame('AUD', $value->currency);
		$this->assertSame('12.50', (string) $value->amount);
	}

	#[Test]
	public function configuring_it_leaves_the_original_alone(): void
	{
		$field = $this->createField();
		$bounded = $field->minAmountOf('AUD', '7.00');

		$this->assertNotSame($field, $bounded);
		$this->assertSame([], $field->minAmounts);
	}

	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$this->assertNull($this->createField()->defaultValue);
	}
}
