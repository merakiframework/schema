<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Property;

class Factory
{
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

	public function createMoney(string $name): Field\Money
	{
		return new Field\Money(new Property\Name($name));
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
}
