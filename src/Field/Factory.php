<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Property;
use InvalidArgumentException;

class Factory
{
	public function __construct(
		/** @var array<string, class-string<Field>> */
		private array $fieldMap = [
			'address' => Field\Address::class,
			'boolean' => Field\Boolean::class,
			'credit_card' => Field\CreditCard::class,
			'date' => Field\Date::class,
			'date_time' => Field\DateTime::class,
			'duration' => Field\Duration::class,
			'email_address' => Field\EmailAddress::class,
			'enum' => Field\Enum::class,
			'file' => Field\File::class,
			'money' => Field\Money::class,
			'name' => Field\Name::class,
			'number' => Field\Number::class,
			'passphrase' => Field\Passphrase::class,
			'password' => Field\Password::class,
			'phone_number' => Field\PhoneNumber::class,
			'text' => Field\Text::class,
			'time' => Field\Time::class,
			'uri' => Field\Uri::class,
			'uuid' => Field\Uuid::class,
			'variant' => Field\Variant::class,
		],
	) {
	}

	public function createAddress(string $name): Field\Address
	{
		return new Field\Address(new Property\Name($name));
	}

	public function createBoolean(string $name): Field\Boolean
	{
		return new Field\Boolean(new Property\Name($name));
	}

	public function createCreditCard(string $name): Field\CreditCard
	{
		return new Field\CreditCard(new Property\Name($name));
	}

	public function createDate(string $name): Field\Date
	{
		return new Field\Date(new Property\Name($name));
	}

	public function createDateTime(string $name): Field\DateTime
	{
		return new Field\DateTime(new Property\Name($name));
	}

	public function createDuration(string $name): Field\Duration
	{
		return new Field\Duration(new Property\Name($name));
	}

	public function createEmailAddress(string $name): Field\EmailAddress
	{
		return new Field\EmailAddress(new Property\Name($name));
	}

	public function createEnum(string $name, array $options): Field\Enum
	{
		return new Field\Enum(new Property\Name($name), $options);
	}

	public function createFile(string $name): Field\File
	{
		return new Field\File(new Property\Name($name));
	}

	/**
	 * @param $allowedCurrencies array<string, integer>
	 */
	public function createMoney(string $name, array $allowedCurrencies): Field\Money
	{
		return new Field\Money(new Property\Name($name), $allowedCurrencies);
	}

	public function createName(string $name): Field\Name
	{
		return new Field\Name(new Property\Name($name));
	}

	public function createNumber(string $name): Field\Number
	{
		return new Field\Number(new Property\Name($name));
	}

	public function createPassphrase(string $name): Field\Passphrase
	{
		return new Field\Passphrase(new Property\Name($name));
	}

	public function createPassword(string $name): Field\Password
	{
		return new Field\Password(new Property\Name($name));
	}

	public function createPhoneNumber(string $name): Field\PhoneNumber
	{
		return new Field\PhoneNumber(new Property\Name($name));
	}

	public function createText(string $name): Field\Text
	{
		return new Field\Text(new Property\Name($name));
	}

	public function createTime(string $name): Field\Time
	{
		return new Field\Time(new Property\Name($name));
	}

	public function createUri(string $name): Field\Uri
	{
		return new Field\Uri(new Property\Name($name));
	}

	public function createUuid(string $name): Field\Uuid
	{
		return new Field\Uuid(new Property\Name($name));
	}

	public function createVariant(string $name, Field\Atomic ...$fields): Field\Variant
	{
		return new Field\Variant(new Property\Name($name), ...$fields);
	}

	public function deserialize(object $data): Field
	{
		if (!isset($data->type)) {
			throw new InvalidArgumentException('Field type is missing.');
		}

		if (!isset($this->fieldMap[$data->type])) {
			throw new InvalidArgumentException('Unknown field type: ' . $data->action);
		}

		$class = $this->fieldMap[$data->type];

		return $class::deserialize($data, $this);
	}
}
