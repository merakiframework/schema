<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Field\CompositeTestCase;
use Meraki\Schema\Field\CreditCard;
use Meraki\Schema\Property\Name;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[Group('field')]
#[CoversClass(CreditCard::class)]
final class CreditCardTest extends CompositeTestCase
{
	public function createSubject(): CreditCard
	{
		return new CreditCard(new Name('credit_card'));
	}

	public function createField(): CreditCard
	{
		return $this->createSubject();
	}

	#[Test]
	public function subfields_are_created(): void
	{
		$field = $this->createSubject();

		$this->assertInstanceOf(Field\Name::class, $field->holder);
		$this->assertInstanceOf(Field\Text::class, $field->number);
		$this->assertInstanceOf(Field\Date::class, $field->expiry);
		$this->assertInstanceOf(Field\Text::class, $field->securityCode);
	}

	#[Test]
	public function subfields_have_correct_names(): void
	{
		$field = $this->createSubject();

		$this->assertEquals('credit_card.holder', (string)$field->holder->name);
		$this->assertEquals('credit_card.number', (string)$field->number->name);
		$this->assertEquals('credit_card.expiry', (string)$field->expiry->name);
		$this->assertEquals('credit_card.security_code', (string)$field->securityCode->name);
	}

	#[Test]
	#[DataProvider('validCreditCards')]
	public function it_validates_valid_credit_cards(string $holder, string $number, string $expiry, string $securityCode): void
	{
		$field = $this->createSubject()->input([
			'holder' =>$holder,
			'number' =>$number,
			'expiry' =>$expiry,
			'security_code' =>$securityCode,
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultPassedForField('credit_card.holder', 'type', $result);

		$this->assertConstraintValidationResultPassedForField('credit_card.number', 'type', $result);
		$this->assertConstraintValidationResultPassedForField('credit_card.number', 'min', $result);
		$this->assertConstraintValidationResultPassedForField('credit_card.number', 'max', $result);

		$this->assertConstraintValidationResultPassedForField('credit_card.expiry', 'type', $result);

		$this->assertConstraintValidationResultPassedForField('credit_card.security_code', 'type', $result);
		$this->assertConstraintValidationResultPassedForField('credit_card.security_code', 'min', $result);
		$this->assertConstraintValidationResultPassedForField('credit_card.security_code', 'max', $result);
	}

	public static function validCreditCards(): array
	{
		// https://www.creditscardgenerator.com/
		return [
			'visa (13 digits)' => ['Matthew James', '4267 7724 0310 2', '2026-04', '242'],
			'visa (16 digits)' => ['Kenneth Miller MD', '4014 1828 2909 8807', '2027-10', '936'],
			'visa (19 digits)' => ['Ryan Hall', '4958 5581 8834 3371 583', '2027-12', '449'],
			'mastercard' => ['Brandon Ramirez', '2720 0792 6056 8240', '2026-12', '594'],
			'amex' => ['Elizabeth Cooper', '3407 769523 04412', '2029-04', '9635'],
			'discover (16 digits)' => ['Mark Lopez', '6466 5921 0488 9001', '2027-02', '960'],
			'discover (19 digits)' => ['Skylar Reed', '6469 8745 3650 4031 151', '2028-03', '289'],
			'diners club (14 digits)' => ['William Morgan', '3038 4195 6843 39', '2028-09', '038'],
			'diners club (16 digits)' => ['Audrey Price III', '3882 2342 3459 5629', '2028-08', '803'],
			'diners club (19 digits)' => ['William Murphy Jr.', '3049 1782 8122 4673 778', '2030-05', '067'],
			'jcb (16 digits)' => ['Mark Williams PhD', '3529 7754 7388 9643', '2027-07', '446'],
			'jcb (19 digits)' => ['Victoria Smith', '3579 7188 3488 3186 616', '2026-10', '198'],
		];
	}

	#[Test]
	public function it_fails_if_holder_is_not_provided(): void
	{
		$field = $this->createSubject()->input([
			'holder' =>'',
			'number' =>'4014 1828 2909 8807',
			'expiry' =>'2027-10',
			'security_code' =>'936',
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('credit_card.holder', 'type', $result);
	}

	#[Test]
	public function it_fails_if_number_is_not_provided(): void
	{
		$field = $this->createSubject()->input([
			'holder' =>'Kenneth Miller MD',
			'number' =>'',
			'expiry' =>'2027-10',
			'security_code' =>'936',
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('credit_card.number', 'min', $result);
	}

	#[Test]
	public function it_fails_if_number_is_too_short(): void
	{
		$field = $this->createSubject()->input([
			'holder' =>'Kenneth Miller MD',
			'number' =>'4014 1828 2909',
			'expiry' =>'2027-10',
			'security_code' =>'936',
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('credit_card.number', 'min', $result);
	}

	#[Test]
	public function it_fails_if_number_is_too_long(): void
	{
		$field = $this->createSubject()->input([
			'holder' =>'Kenneth Miller MD',
			'number' =>'4958 5581 8834 3371 5819',
			'expiry' =>'2027-10',
			'security_code' =>'936',
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('credit_card.number', 'max', $result);
	}

	#[Test]
	public function it_fails_if_security_code_is_not_provided(): void
	{
		$field = $this->createSubject()->input([
			'holder' =>'Kenneth Miller MD',
			'number' =>'4014 1828 2909 8807',
			'expiry' =>'2027-10',
			'security_code' =>'',
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('credit_card.security_code', 'min', $result);
	}

	#[Test]
	public function it_fails_if_security_code_is_too_short(): void
	{
		$field = $this->createSubject()->input([
			'holder' =>'Kenneth Miller MD',
			'number' =>'4014 1828 2909 8807',
			'expiry' =>'2027-10',
			'security_code' =>'93',
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('credit_card.security_code', 'min', $result);
	}

	#[Test]
	public function it_fails_if_security_code_is_too_long(): void
	{
		$field = $this->createSubject()->input([
			'holder' =>'Kenneth Miller MD',
			'number' =>'4014 1828 2909 8807',
			'expiry' =>'2027-10',
			'security_code' =>'93675',
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('credit_card.security_code', 'max', $result);
	}

	#[Test]
	public function it_has_no_value_by_default(): void
	{
		$field = $this->createSubject();

		$this->assertEquals(
			['credit_card.holder' => null, 'credit_card.number' => null, 'credit_card.expiry' => null, 'credit_card.security_code' => null],
			$field->value->unwrap()
		);
		$this->assertEquals(null, $field->holder->value->unwrap());
		$this->assertEquals(null, $field->number->value->unwrap());
		$this->assertEquals(null, $field->expiry->value->unwrap());
		$this->assertEquals(null, $field->securityCode->value->unwrap());
	}

	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$field = $this->createSubject();

		$this->assertEquals(
			['credit_card.holder' => null, 'credit_card.number' => null, 'credit_card.expiry' => null, 'credit_card.security_code' => null],
			$field->value->unwrap()
		);
		$this->assertEquals(null, $field->holder->defaultValue->unwrap());
		$this->assertEquals(null, $field->number->defaultValue->unwrap());
		$this->assertEquals(null, $field->expiry->defaultValue->unwrap());
		$this->assertEquals(null, $field->securityCode->defaultValue->unwrap());
	}

	#[Test]
	public function it_fails_if_expiry_is_not_provided(): void
	{
		$field = $this->createSubject()->input([
			'holder' =>'Kenneth Miller MD',
			'number' =>'4014 1828 2909 8807',
			'expiry' =>'',
			'security_code' =>'936',
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('credit_card.expiry', 'type', $result);
	}

	#[Test]
	public function it_fails_if_expiry_is_in_the_past(): void
	{
		$field = $this->createSubject()->input([
			'holder' =>'Kenneth Miller MD',
			'number' =>'4014 1828 2909 8807',
			'expiry' =>'2020-01',
			'security_code' =>'936',
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('credit_card.expiry', 'from', $result);
	}

	#[Test]
	public function end_of_month_day_is_automatically_added_to_end_of_expiry_date(): void
	{
		$field = $this->createSubject()->input([
			'holder' =>'Kenneth Miller MD',
			'number' =>'4014 1828 2909 8807',
			'expiry' =>'2029-07',
			'security_code' =>'936',
		]);

		$result = $field->validate();

		$this->assertEquals('2029-07-31', $field->expiry->resolvedValue->unwrap());
	}
}
