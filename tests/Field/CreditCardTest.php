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
			'credit_card.holder' => $holder,
			'credit_card.number' => $number,
			'credit_card.expiry' => $expiry,
			'credit_card.security_code' => $securityCode,
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
			'visa (13 digits)' => ['Matthew James', '4267 7724 0310 5', '2026-04', '242'],
			'visa (16 digits)' => ['Kenneth Miller MD', '4014 1828 2909 8805', '2027-10', '936'],
			'visa (19 digits)' => ['Ryan Hall', '4958 5581 8834 3371 581', '2027-12', '449'],
			'mastercard' => ['Brandon Ramirez', '2720 0792 6056 8243', '2026-12', '594'],
			'amex' => ['Elizabeth Cooper', '3407 769523 04418', '2029-04', '9635'],
			'discover (16 digits)' => ['Mark Lopez', '6466 5921 0488 9007', '2027-02', '960'],
			'discover (19 digits)' => ['Skylar Reed', '6469 8745 3650 4031 159', '2028-03', '289'],
			'diners club (14 digits)' => ['William Morgan', '3038 4195 6843 35', '2028-09', '038'],
			'diners club (16 digits)' => ['Audrey Price III', '3882 2342 3459 5627', '2028-08', '803'],
			'diners club (19 digits)' => ['William Murphy Jr.', '3049 1782 8122 4673 771', '2030-05', '067'],
			'jcb (16 digits)' => ['Mark Williams PhD', '3529 7754 7388 9646', '2027-07', '446'],
			'jcb (19 digits)' => ['Victoria Smith', '3579 7188 3488 3186 615', '2026-10', '198'],
		];
	}

	#[Test]
	public function it_fails_if_holder_is_not_provided(): void
	{
		$field = $this->createSubject()->input([
			'credit_card.holder' => '',
			'credit_card.number' => '4014 1828 2909 8805',
			'credit_card.expiry' => '2027-10',
			'credit_card.security_code' => '936',
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('credit_card.holder', 'type', $result);
	}

	#[Test]
	public function it_fails_if_number_is_not_provided(): void
	{
		$field = $this->createSubject()->input([
			'credit_card.holder' => 'Kenneth Miller MD',
			'credit_card.number' => '',
			'credit_card.expiry' => '2027-10',
			'credit_card.security_code' => '936',
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('credit_card.number', 'min', $result);
	}

	#[Test]
	public function it_fails_if_number_is_too_short(): void
	{
		$field = $this->createSubject()->input([
			'credit_card.holder' => 'Kenneth Miller MD',
			'credit_card.number' => '4014 1828 2909',
			'credit_card.expiry' => '2027-10',
			'credit_card.security_code' => '936',
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('credit_card.number', 'min', $result);
	}

	#[Test]
	public function it_fails_if_number_is_too_long(): void
	{
		$field = $this->createSubject()->input([
			'credit_card.holder' => 'Kenneth Miller MD',
			'credit_card.number' => '4958 5581 8834 3371 5812',
			'credit_card.expiry' => '2027-10',
			'credit_card.security_code' => '936',
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('credit_card.number', 'max', $result);
	}

	#[Test]
	public function it_fails_if_security_code_is_not_provided(): void
	{
		$field = $this->createSubject()->input([
			'credit_card.holder' => 'Kenneth Miller MD',
			'credit_card.number' => '4014 1828 2909 8805',
			'credit_card.expiry' => '2027-10',
			'credit_card.security_code' => '',
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('credit_card.security_code', 'min', $result);
	}

	#[Test]
	public function it_fails_if_security_code_is_too_short(): void
	{
		$field = $this->createSubject()->input([
			'credit_card.holder' => 'Kenneth Miller MD',
			'credit_card.number' => '4014 1828 2909 8805',
			'credit_card.expiry' => '2027-10',
			'credit_card.security_code' => '93',
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('credit_card.security_code', 'min', $result);
	}

	#[Test]
	public function it_fails_if_security_code_is_too_long(): void
	{
		$field = $this->createSubject()->input([
			'credit_card.holder' => 'Kenneth Miller MD',
			'credit_card.number' => '4014 1828 2909 8805',
			'credit_card.expiry' => '2027-10',
			'credit_card.security_code' => '93675',
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
			'credit_card.holder' => 'Kenneth Miller MD',
			'credit_card.number' => '4014 1828 2909 8805',
			'credit_card.expiry' => '',
			'credit_card.security_code' => '936',
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('credit_card.expiry', 'type', $result);
	}

	#[Test]
	public function it_fails_if_expiry_is_in_the_past(): void
	{
		$field = $this->createSubject()->input([
			'credit_card.holder' => 'Kenneth Miller MD',
			'credit_card.number' => '4014 1828 2909 8805',
			'credit_card.expiry' => '2020-01',
			'credit_card.security_code' => '936',
		]);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('credit_card.expiry', 'from', $result);
	}

	#[Test]
	public function end_of_month_day_is_automatically_added_to_end_of_expiry_date(): void
	{
		$field = $this->createSubject()->input([
			'credit_card.holder' => 'Kenneth Miller MD',
			'credit_card.number' => '4014 1828 2909 8805',
			'credit_card.expiry' => '2029-07',
			'credit_card.security_code' => '936',
		]);

		$result = $field->validate();

		$this->assertEquals('2029-07-31', $field->expiry->resolvedValue->unwrap());
	}

	#[Test]
	public function it_serializes_and_deserializes(): void
	{
		$addressNormalized = [
			'credit_card.holder' => 'Mr. Matthew James',
			'credit_card.number' => '4014182829098805',
			'credit_card.expiry' => '2027-10-31',
			'credit_card.security_code' => '511',
		];
		$sut = $this->createSubject()
			->prefill([
				'credit_card.holder' => 'Mr. Matthew James',
				'credit_card.number' => '4014 1828 2909 8805',
				'credit_card.expiry' => '2027-10',
				'credit_card.security_code' => '511',
			]);

		$serialized = $sut->serialize();

		// serializing normalises date and number
		$this->assertEquals('credit_card', $serialized->type);
		$this->assertEquals('credit_card', $serialized->name);
		$this->assertFalse($serialized->optional);
		$this->assertEquals($addressNormalized, $serialized->value);

		$deserialized = CreditCard::deserialize($serialized);

		$this->assertEquals('credit_card', $deserialized->type->value);
		$this->assertEquals('credit_card', $deserialized->name->value);
		$this->assertFalse($deserialized->optional);
		$this->assertEquals($addressNormalized, $deserialized->defaultValue->unwrap());
	}

	#[Test]
	public function children_returns_serialized_fields(): void
	{
		$field = $this->createSubject()->prefill([
			'credit_card.holder' => 'Mr. Matthew James',
			'credit_card.number' => '4014 1828 2909 8805',
			'credit_card.expiry' => '2027-10',
			'credit_card.security_code' => '511',
		]);
		$serialized = $field->serialize();
		$children = $serialized->children();

		$this->assertCount(4, $children);
		$this->assertSerializedChildrenContainsFieldWithNameOf('credit_card.holder', $children);
		$this->assertSerializedChildrenContainsFieldWithNameOf('credit_card.number', $children);
		$this->assertSerializedChildrenContainsFieldWithNameOf('credit_card.expiry', $children);
		$this->assertSerializedChildrenContainsFieldWithNameOf('credit_card.security_code', $children);
	}
}
