<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Field\Factory;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('field')]
#[CoversClass(Factory::class)]
final class FactoryTest extends TestCase
{
	#[Test]
	#[DataProvider('predefinedFields')]
	public function it_can_create_predefined_fields(string $fieldName, string $methodToCall, string $fieldClass): void
	{
		$factory = new Factory();

		$field = call_user_func([$factory, $methodToCall], $fieldName);

		$this->assertInstanceOf(Field::class, $field);
		$this->assertInstanceOf($fieldClass, $field);
		$this->assertEquals($fieldName, (string)$field->name);
	}

	public static function predefinedFields(): array
	{
		return [
			'address' => ['billing_address', 'createAddress', Field\Address::class],
			'boolean' => ['agree_to_terms_and_conditions', 'createBoolean', Field\Boolean::class],
			'credit_card' => ['credit_card', 'createCreditCard', Field\CreditCard::class],
			'date' => ['date_of_birth', 'createDate', Field\Date::class],
			'date_time' => ['appointment_time', 'createDateTime', Field\DateTime::class],
			'duration' => ['duration', 'createDuration', Field\Duration::class],
			'email_address' => ['email_address', 'createEmailAddress', Field\EmailAddress::class],
			'file' => ['resume', 'createFile', Field\File::class],
			'money' => ['salary', 'createMoney', Field\Money::class],
			'name' => ['full_name', 'createName', Field\Name::class],
			'number' => ['age', 'createNumber', Field\Number::class],
			'passphrase' => ['passphrase', 'createPassphrase', Field\Passphrase::class],
			'password' => ['password', 'createPassword', Field\Password::class],
			'phone_number' => ['phone_number', 'createPhoneNumber', Field\PhoneNumber::class],
			'text' => ['description', 'createText', Field\Text::class],
			'time' => ['meeting_time', 'createTime', Field\Time::class],
			'url' => ['website', 'createUrl', Field\Url::class],
			'uuid' => ['user_id', 'createUuid', Field\Uuid::class],
		];
	}
}
