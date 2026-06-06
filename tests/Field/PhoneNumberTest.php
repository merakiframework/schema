<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\PhoneNumber;
use Meraki\Schema\Field\PhoneNumber\Type;
use Meraki\Schema\Property\Name;
use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\FieldTestCase;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberFormat;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('field')]
#[CoversClass(PhoneNumber::class)]
#[CoversClass(Type::class)]
final class PhoneNumberTest extends FieldTestCase
{
	public function createField(): PhoneNumber
	{
		return new PhoneNumber(new Name('test'));
	}

	#[Test]
	public function it_has_the_correct_name(): void
	{
		$field = $this->createField();

		$this->assertSame('test', $field->name->value);
	}

	#[Test]
	public function it_is_an_atomic_field(): void
	{
		$field = $this->createField();

		$this->assertInstanceOf(AtomicField::class, $field);
	}

	#[Test]
	public function with_no_allowed_countries_it_accepts_a_valid_international_number(): void
	{
		$result = (new PhoneNumber(new Name('test')))
			->input($this->example('AU', PhoneNumberType::MOBILE, PhoneNumberFormat::E164))
			->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
	}

	#[Test]
	public function with_no_allowed_countries_it_rejects_a_local_number(): void
	{
		// A national-format number has no country context to validate against.
		$result = (new PhoneNumber(new Name('test')))
			->input($this->example('AU', PhoneNumberType::MOBILE, PhoneNumberFormat::NATIONAL))
			->validate();

		$this->assertConstraintValidationResultFailed('type', $result);
	}

	#[Test]
	public function it_rejects_a_value_that_is_not_a_phone_number(): void
	{
		$result = (new PhoneNumber(new Name('test'), ['AU']))
			->input('0000')
			->validate();

		$this->assertConstraintValidationResultFailed('type', $result);
	}

	#[Test]
	public function it_accepts_a_local_number_for_an_allowed_country(): void
	{
		$result = (new PhoneNumber(new Name('test'), ['AU']))
			->input($this->example('AU', PhoneNumberType::MOBILE, PhoneNumberFormat::NATIONAL))
			->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultPassed('allowedCountries', $result);
	}

	#[Test]
	public function it_accepts_an_international_number_for_an_allowed_country(): void
	{
		$result = (new PhoneNumber(new Name('test'), ['AU']))
			->input($this->example('AU', PhoneNumberType::MOBILE, PhoneNumberFormat::E164))
			->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultPassed('allowedCountries', $result);
	}

	#[Test]
	public function it_rejects_an_international_number_from_a_disallowed_country(): void
	{
		// A valid number, but from outside the allowed set: the shape is fine, the
		// country is not.
		$result = (new PhoneNumber(new Name('test'), ['AU']))
			->input($this->example('US', PhoneNumberType::FIXED_LINE_OR_MOBILE, PhoneNumberFormat::E164))
			->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultFailed('allowedCountries', $result);
	}

	#[Test]
	public function allowed_countries_is_skipped_when_none_are_configured(): void
	{
		$result = (new PhoneNumber(new Name('test')))
			->input($this->example('AU', PhoneNumberType::MOBILE, PhoneNumberFormat::E164))
			->validate();

		$this->assertConstraintValidationResultSkipped('allowedCountries', $result);
	}

	#[Test]
	public function it_accepts_a_number_of_the_required_type(): void
	{
		$result = (new PhoneNumber(new Name('test'), ['AU']))->ofType(Type::Mobile)
			->input($this->example('AU', PhoneNumberType::MOBILE, PhoneNumberFormat::NATIONAL))
			->validate();

		$this->assertConstraintValidationResultPassed('numberType', $result);
	}

	#[Test]
	public function it_rejects_a_number_of_the_wrong_type(): void
	{
		$result = (new PhoneNumber(new Name('test'), ['AU']))->ofType(Type::Mobile)
			->input($this->example('AU', PhoneNumberType::FIXED_LINE, PhoneNumberFormat::NATIONAL))
			->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultFailed('numberType', $result);
	}

	#[Test]
	public function number_type_is_skipped_when_unrestricted(): void
	{
		$result = (new PhoneNumber(new Name('test'), ['AU']))
			->input($this->example('AU', PhoneNumberType::FIXED_LINE, PhoneNumberFormat::NATIONAL))
			->validate();

		$this->assertConstraintValidationResultSkipped('numberType', $result);
	}

	#[Test]
	public function it_throws_when_allowing_an_unsupported_region(): void
	{
		$this->expectException(\InvalidArgumentException::class);

		new PhoneNumber(new Name('test'), ['ZZ']);
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

	private function example(string $region, int $type, int $format): string
	{
		$util = PhoneNumberUtil::getInstance();

		return $util->format($util->getExampleNumberForType($region, $type), $format);
	}
}
