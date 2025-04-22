<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Field\Type;

final class Factory
{
	private static $registeredTypes = [];

	public function __construct(
		array $registeredTypes = []
	) {
		$this->registerDefault();

		foreach ($registeredTypes as $name => $class) {
			$this->register($name, $class);
		}
	}

	private function registerDefault(): void
	{
		$this->register('address', fn(): Type\Address => new Type\Address());
		$this->register('boolean', fn(): Type\Boolean => new Type\Boolean());
		$this->register('credit_card', fn(): Type\CreditCard => new Type\CreditCard());
		$this->register('date', fn(): Type\Date => new Type\Date());
		$this->register('date_time', fn(): Type\DateTime => new Type\DateTime());
		$this->register('duration', fn(): Type\Duration => new Type\Duration());
		$this->register('email_address', fn(): Type\EmailAddress => new Type\EmailAddress());
		$this->register('enum', fn(): Type\Enum => new Type\Enum());
		$this->register('file', fn(): Type\File => new Type\File());
		$this->register('money', fn(): Type\Money => new Type\Money());
		$this->register('name', fn(): Type\Name => new Type\Name());
		$this->register('number', fn(): Type\Number => new Type\Number());
		$this->register('passphrase', fn(): Type\Passphrase => new Type\Passphrase());
		$this->register('password', fn(): Type\Password => new Type\Password());
		$this->register('phone_number', fn(): Type\PhoneNumber => new Type\PhoneNumber());
		$this->register('text', fn(): Type\Text => new Type\Text());
		$this->register('time', fn(): Type\Time => new Type\Time());
		$this->register('uuid', fn(): Type\Uuid => new Type\Uuid());
	}

	/**
	 * Registers a new field type and the class that represents it.
	 *
	 * @param string $typeName The name of the field type, as used in the schema.
	 * @param callable(): Type $factory The fully-qualified class name that represents the field.
	 */
	public function register(string $typeName, callable $factory): void
	{
		self::$registeredTypes[$typeName] = $factory;
	}

	/**
	 * Create a new field of the given type. This will only
	 */
	public function create(string $type, string $name): Field
	{
		return $this->createOfType($type, $name);
	}

	public function createUuid(string $name): Field
	{
		return $this->createOfType('uuid', $name);
	}

	public function createDuration(string $name): Field
	{
		return $this->createOfType('duration', $name);
	}

	public function createName(string $name): Field
	{
		return $this->createOfType('name', $name);
	}

	public function createEnum(string $name, array $allowedValues): Field
	{
		return $this->createOfType('enum', $name);
	}

	public function createText(string $name): Field
	{
		return $this->createOfType('text', $name);
	}

	public function createEmailAddress(string $name): Field
	{
		return $this->createOfType('email_address', $name);
	}

	public function createPhoneNumber(string $name): Field
	{
		return $this->createOfType('phone_number', $name);
	}

	public function createNumber(string $name): Field
	{
		return $this->createOfType('number', $name);
	}

	public function createAddress(string $name): Field
	{
		return $this->createOfType('address', $name);
	}

	public function createPassword(string $name): Field
	{
		return $this->createOfType('password', $name);
	}

	public function createPassphrase(string $name): Field
	{
		return $this->createOfType('passphrase', $name);
	}

	public function createDate(string $name): Field
	{
		return $this->createOfType('date', $name);
	}

	public function createDateTime(string $name): Field
	{
		return $this->createOfType('date_time', $name);
	}

	public function createTime(string $name): Field
	{
		return $this->createOfType('time', $name);
	}

	public function createFile(string $name): Field
	{
		return $this->createOfType('file', $name);
	}

	public function createCreditCard(string $name): Field
	{
		return $this->createOfType('credit_card', $name);
	}

	public function createBoolean(string $name): Field
	{
		return $this->createOfType('boolean', $name);
	}

	public function createMoney(string $name): Field
	{
		return $this->createOfType('money', $name);
	}

	public function createOfType(string $type, string $name): Field
	{
		if (!array_key_exists($type, self::$registeredTypes)) {
			throw new \InvalidArgumentException("Could not create field of type '{$type}': no class registered.");
		}

		$factory = self::$registeredTypes[$type];

		return new Field($factory(), new Name($name));
	}
}
