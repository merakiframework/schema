<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Field\Factory;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('field')]
#[CoversClass(Factory::class)]
final class FactoryTest extends TestCase
{
	#[Test]
	public function it_can_create_a_field_with_an_address_type(): void
	{
		$fieldName = 'billing_address';
		$factory = new Factory();
		$field = $factory->createAddress($fieldName);

		$this->assertInstanceOf(Field::class, $field);
		$this->assertInstanceOf(Type\Address::class, $field->type);
		$this->assertSame($fieldName, (string)$field->name);
	}

	#[Test]
	public function it_can_create_a_field_with_a_boolean_type(): void
	{
		$fieldName = 'agree_to_terms_and_conditions';
		$factory = new Factory();
		$field = $factory->createBoolean($fieldName);

		$this->assertInstanceOf(Field::class, $field);
		$this->assertInstanceOf(Type\Boolean::class, $field->type);
		$this->assertSame($fieldName, (string)$field->name);
	}

	#[Test]
	public function it_can_create_a_field_with_a_credit_card_type(): void
	{
		$fieldName = 'credit_card';
		$factory = new Factory();
		$field = $factory->createCreditCard($fieldName);

		$this->assertInstanceOf(Field::class, $field);
		$this->assertInstanceOf(Type\CreditCard::class, $field->type);
		$this->assertSame($fieldName, (string)$field->name);
	}

	#[Test]
	public function it_can_create_a_field_with_a_date_type(): void
	{
		$fieldName = 'date_of_birth';
		$factory = new Factory();
		$field = $factory->createDate($fieldName);

		$this->assertInstanceOf(Field::class, $field);
		$this->assertInstanceOf(Type\Date::class, $field->type);
		$this->assertSame($fieldName, (string)$field->name);
	}

	#[Test]
	public function it_can_create_a_field_with_a_date_time_type(): void
	{
		$fieldName = 'appointment_time';
		$factory = new Factory();
		$field = $factory->createDateTime($fieldName);

		$this->assertInstanceOf(Field::class, $field);
		$this->assertInstanceOf(Type\DateTime::class, $field->type);
		$this->assertSame($fieldName, (string)$field->name);
	}

	#[Test]
	public function it_can_create_a_field_with_a_duration_type(): void
	{
		$fieldName = 'duration';
		$factory = new Factory();
		$field = $factory->createDuration($fieldName);

		$this->assertInstanceOf(Field::class, $field);
		$this->assertInstanceOf(Type\Duration::class, $field->type);
		$this->assertSame($fieldName, (string)$field->name);
	}

	#[Test]
	public function it_can_create_a_field_with_an_email_address_type(): void
	{
		$fieldName = 'email_address';
		$factory = new Factory();
		$field = $factory->createEmailAddress($fieldName);

		$this->assertInstanceOf(Field::class, $field);
		$this->assertInstanceOf(Type\EmailAddress::class, $field->type);
		$this->assertSame($fieldName, (string)$field->name);
	}

	#[Test]
	public function it_can_create_a_field_with_a_file_type(): void
	{
		$fieldName = 'resume';
		$factory = new Factory();
		$field = $factory->createFile($fieldName);

		$this->assertInstanceOf(Field::class, $field);
		$this->assertInstanceOf(Type\File::class, $field->type);
		$this->assertSame($fieldName, (string)$field->name);
	}

	#[Test]
	public function it_can_create_a_field_with_a_money_type(): void
	{
		$fieldName = 'salary';
		$factory = new Factory();
		$field = $factory->createMoney($fieldName);

		$this->assertInstanceOf(Field::class, $field);
		$this->assertInstanceOf(Type\Money::class, $field->type);
		$this->assertSame($fieldName, (string)$field->name);
	}

	#[Test]
	public function it_can_create_a_field_with_a_name_type(): void
	{
		$fieldName = 'full_name';
		$factory = new Factory();
		$field = $factory->createName($fieldName);

		$this->assertInstanceOf(Field::class, $field);
		$this->assertInstanceOf(Type\Name::class, $field->type);
		$this->assertSame($fieldName, (string)$field->name);
	}

	#[Test]
	public function it_can_create_a_field_with_a_number_type(): void
	{
		$fieldName = 'age';
		$factory = new Factory();
		$field = $factory->createNumber($fieldName);

		$this->assertInstanceOf(Field::class, $field);
		$this->assertInstanceOf(Type\Number::class, $field->type);
		$this->assertSame($fieldName, (string)$field->name);
	}

	#[Test]
	public function it_can_create_a_field_with_a_passphrase_type(): void
	{
		$fieldName = 'passphrase';
		$factory = new Factory();
		$field = $factory->createPassphrase($fieldName);

		$this->assertInstanceOf(Field::class, $field);
		$this->assertInstanceOf(Type\Passphrase::class, $field->type);
		$this->assertSame($fieldName, (string) $field->name);
	}

	#[Test]
	public function it_can_create_a_field_with_a_password_type(): void
	{
		$fieldName = 'password';
		$factory = new Factory();
		$field = $factory->createPassword($fieldName);

		$this->assertInstanceOf(Field::class, $field);
		$this->assertInstanceOf(Type\Password::class, $field->type);
		$this->assertSame($fieldName, (string)$field->name);
	}

	#[Test]
	public function it_can_create_a_field_with_a_phone_number_type(): void
	{
		$fieldName = 'phone_number';
		$factory = new Factory();
		$field = $factory->createPhoneNumber($fieldName);

		$this->assertInstanceOf(Field::class, $field);
		$this->assertInstanceOf(Type\PhoneNumber::class, $field->type);
		$this->assertSame($fieldName, (string)$field->name);
	}

	#[Test]
	public function it_can_create_a_field_with_a_text_type(): void
	{
		$fieldName = 'description';
		$factory = new Factory();
		$field = $factory->createText($fieldName);

		$this->assertInstanceOf(Field::class, $field);
		$this->assertInstanceOf(Type\Text::class, $field->type);
		$this->assertSame($fieldName, (string)$field->name);
	}

	#[Test]
	public function it_can_create_a_field_with_a_time_type(): void
	{
		$fieldName = 'meeting_time';
		$factory = new Factory();
		$field = $factory->createTime($fieldName);

		$this->assertInstanceOf(Field::class, $field);
		$this->assertInstanceOf(Type\Time::class, $field->type);
		$this->assertSame($fieldName, (string)$field->name);
	}

	#[Test]
	public function it_can_create_a_field_with_a_uuid_type(): void
	{
		$fieldName = 'user_id';
		$factory = new Factory();
		$field = $factory->createUuid($fieldName);

		$this->assertInstanceOf(Field::class, $field);
		$this->assertInstanceOf(Type\Uuid::class, $field->type);
		$this->assertSame($fieldName, (string)$field->name);
	}
}
