<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;


use Meraki\Schema\Field\PhoneNumber;
use Meraki\Schema\Property\Name;
use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[Group('field')]
#[CoversClass(PhoneNumber::class)]
final class PhoneNumberTest extends FieldTestCase
{
	public function createField(): PhoneNumber
	{
		return new PhoneNumber(new Name('test'));
	}

	#[Test]
	public function it_has_the_correct_type(): void
	{
		$field = $this->createField();

		$this->assertSame('phone_number', $field->type->value);
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
	#[DataProvider('validPhoneNumbers')]
	public function it_validates_valid_phone_numbers(string $phoneNumber): void
	{
		$type = $this->createField()
			->input($phoneNumber);

		$result = $type->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
	}

	#[Test]
	#[DataProvider('invalidPhoneNumbers')]
	public function it_does_not_validate_invalid_phone_numbers(string $phoneNumber): void
	{
		$type = $this->createField()
			->input($phoneNumber);

		$result = $type->validate();

		$this->assertConstraintValidationResultFailed('type', $result);
	}

	public static function validPhoneNumbers(): array
	{
		return [
			'minimum length no spaces' => ['+61'],
			'maximum length no spaces' => ['+613123456789012'],
			'minimum length with spaces' => ['+6 3'],
			'maximum length with spaces' => ['+61 3 1234 5678 9012'],
			'parentheses' => ['+61 (3) 1234 5678'],
			'hyphens' => ['+61-3-1234-5678'],
			'periods' => ['+61.3.1234.5678'],
			'mixed separators' => ['+61 (3) 1234-5678'],
			'mixed separators at max length' => ['+61 (3) 1234 5678 9012'],
		];
	}

	public static function invalidPhoneNumbers(): array
	{
		return [
			'missing "+" prefix' => ['61 3 1234 5678'],
			'with invalid characters' => ['+61 3 1234 5678a'],
			'too short' => ['+6'],
			'too long' => ['+6131234567890123'],
			'with invalid separators' => ['+61/3/1234/5678'],
			'too long with separators' => ['+61 (3) 1234 5678 90123'],
			'contains formatting characters proceeding country code' => ['+ 61 3 1234 5678'],
			'contains formatting characters around country code' => ['+(61) 3 1234 5678'],
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

	#[Test]
	public function it_serializes_and_deserializes(): void
	{
		$sut = $this->createField()
			->makeOptional()
			->prefill('+61 3 1234 5678');

		$serialized = $sut->serialize();

		// serializing normalises the phone number
		$this->assertEquals('phone_number', $serialized->type);
		$this->assertEquals('test', $serialized->name);
		$this->assertTrue($serialized->optional);
		$this->assertEquals('+61312345678', $serialized->value);

		$deserialized = PhoneNumber::deserialize($serialized);

		$this->assertEquals('phone_number', $deserialized->type->value);
		$this->assertEquals('test', $deserialized->name->value);
		$this->assertTrue($deserialized->optional);
		$this->assertEquals('+61312345678', $deserialized->defaultValue->unwrap());
	}
}
