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
	public function it_can_create_predefined_fields(string $methodToCall, string $fieldClass, array $args = []): void
	{
		$factory = new Factory();

		$field = call_user_func_array([$factory, $methodToCall], $args);

		$this->assertInstanceOf(Field::class, $field);
		$this->assertInstanceOf($fieldClass, $field);
		$this->assertEquals($args['name'], (string)$field->name);
	}

	public static function predefinedFields(): array
	{
		return [
			'address' => ['createAddress', Field\Address::class, [
				'name' => 'billing_address',
			]],
			'boolean' => ['createBoolean', Field\Boolean::class, [
				'name' => 'agree_to_terms_and_conditions',
			]],
			'credit_card' => ['createCreditCard', Field\CreditCard::class, [
				'name' => 'credit_card',
			]],
			'date' => ['createDate', Field\Date::class, [
				'name' => 'date_of_birth',
			]],
			'date_time' => ['createDateTime', Field\DateTime::class, [
				'name' => 'appointment_time',
			]],
			'duration' => ['createDuration', Field\Duration::class, [
				'name' => 'project_duration',
			]],
			'email_address' => ['createEmailAddress', Field\EmailAddress::class, [
				'name' => 'contact_email',
			]],
			'enum' => ['createEnum', Field\Enum::class, [
				'name' => 'state_or_territory',
				'options' => ['NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'ACT', 'NT']
			]],
			'file' => ['createFile', Field\File::class, [
				'name' => 'resume',
			]],
			'money' => ['createMoney', Field\Money::class, [
				'name' => 'salary',
			]],
			'name' => ['createName', Field\Name::class, [
				'name' => 'full_name',
			]],
			'number' => ['createNumber', Field\Number::class, [
				'name' => 'age',
			]],
			'passphrase' => ['createPassphrase', Field\Passphrase::class, [
				'name' => 'passphrase',
			]],
			'password' => ['createPassword', Field\Password::class, [
				'name' => 'password',
			]],
			'phone_number' => ['createPhoneNumber', Field\PhoneNumber::class, [
				'name' => 'phone_number',
			]],
			'text' => ['createText', Field\Text::class, [
				'name' => 'description',
			]],
			'time' => ['createTime', Field\Time::class, [
				'name' => 'meeting_time',
			]],
			'uri' => ['createUri', Field\Uri::class, [
				'name' => 'website',
			]],
			'uuid' => ['createUuid', Field\Uuid::class, [
				'name' => 'user_id',
			]],
		];
	}
}
