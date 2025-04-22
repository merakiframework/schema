<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Attribute;
use Meraki\Schema\Field;

final class Factory
{
	private static $registeredFields = [];

	public function __construct(
		array $registeredFields = [],
		public Attribute\Factory $attributeFactory = new Attribute\Factory()
	) {
		$this->registerDefault();

		foreach ($registeredFields as $name => $class) {
			$this->register($name, $class);
		}
	}

	private function registerDefault(): void
	{
		$this->register('address', Field\Address::class);
		$this->register('boolean', Field\Boolean::class);
		$this->register('credit_card', Field\CreditCard::class);
		$this->register('date', Field\Date::class);
		$this->register('date_time', Field\DateTime::class);
		$this->register('duration', Field\Duration::class);
		$this->register('email_address', Field\EmailAddress::class);
		$this->register('enum', Field\Enum::class);
		$this->register('file', Field\File::class);
		$this->register('money', Field\Money::class);
		$this->register('name', Field\Name::class);
		$this->register('number', Field\Number::class);
		$this->register('passphrase', Field\Passphrase::class);
		$this->register('password', Field\Password::class);
		$this->register('phone_number', Field\PhoneNumber::class);
		$this->register('text', Field\Text::class);
		$this->register('time', Field\Time::class);
		$this->register('uuid', Field\Uuid::class);
	}

	/**
	 * Registers a new field type and the class that represents it.
	 *
	 * @param string $name The name of the field, as used in the schema.
	 * @param class-string $class The fully-qualified class name that represents the field.
	 */
	public function register(string $name, string $class): void
	{
		self::$registeredFields[$name] = $class;
	}

	/**
	 * Create a new field of the given type. This will only
	 */
	public function create(string $type, string $name, array $attrs): Field
	{
		return $this->createOfType($type, $name, $attrs);
	}

	public function createUuid(string $name, array $attrs = []): Field\Uuid
	{
		return $this->createOfType('uuid', $name, $attrs);
	}

	public function createDuration(string $name, array $attrs = []): Field\Duration
	{
		return $this->createOfType('duration', $name, $attrs);
	}

	public function createName(string $name): Field\Name
	{
		return new Field\Name(new Attribute\Name($name));
	}

	public function createEnum(string $name, array $allowedValues): Field\Enum
	{
		return new Field\Enum(new Attribute\Name($name), new Attribute\OneOf($allowedValues));
	}

	public function createText(string $name): Field\Text
	{
		return new Field\Text(new Attribute\Name($name));
	}

	public function createEmailAddress(string $name): Field\EmailAddress
	{
		return new Field\EmailAddress(new Attribute\Name($name));
	}

	public function createPhoneNumber(string $name): Field\PhoneNumber
	{
		return new Field\PhoneNumber(new Attribute\Name($name));
	}

	public function createNumber(string $name): Field\Number
	{
		return new Field\Number(new Attribute\Name($name));
	}

	public function createAddress(string $name): Field\Address
	{
		return new Field\Address(new Attribute\Name($name));
	}

	public function createPassword(string $name): Field\Password
	{
		return new Field\Password(new Attribute\Name($name));
	}

	public function createPassphrase(string $name): Field\Passphrase
	{
		return new Field\Passphrase(new Attribute\Name($name));
	}

	public function createDate(string $name, array $attrs = []): Field\Date
	{
		return $this->createOfType('date', $name, $attrs);
	}

	public function createDateTime(string $name, array $attrs = []): Field\DateTime
	{
		return $this->createOfType('date_time', $name, $attrs);
	}

	public function createTime(string $name, array $attrs = []): Field\Time
	{
		return $this->createOfType('time', $name, $attrs);
	}

	public function createFile(string $name, array $attrs = []): Field\File
	{
		return $this->createOfType('file', $name, $attrs);
	}

	public function createCreditCard(string $name, array $attrs = []): Field\CreditCard
	{
		return $this->createOfType('credit_card', $name, $attrs);
	}

	public function createBoolean(string $name, array $attrs = []): Field\Boolean
	{
		return $this->createOfType('boolean', $name, $attrs);
	}

	public function createOfType(string $type, string $name, array $attrs = []): Field
	{
		if (!array_key_exists($type, self::$registeredFields)) {
			throw new \InvalidArgumentException("Could not create field of type '{$type}': no class registered.");
		}

		$fieldClass = self::$registeredFields[$type];
		$unorderedAttrs = $this->toAttributes($attrs);
		$orderedAttrs = $this->orderAttributesAccordingToSignature($unorderedAttrs, $fieldClass);

		return new $fieldClass(new Attribute\Name($name), ...$orderedAttrs);
	}

	private function orderAttributesAccordingToSignature(array $attrs, string $fieldClass): array
	{
		$reflection = new \ReflectionClass($fieldClass);
		$constructor = $reflection->getConstructor();
		$orderedAttrs = [];

		foreach ($constructor->getParameters() as $param) {
			$attrType = $param->getType();

			foreach ($attrs as $key => $attr) {
				if ($attrType->getName() === get_class($attr)) {
					$orderedAttrs[] = $attr;
					unset($attrs[$key]);
				}
			}
		}

		// add any remaining attributes that were not matched to the end
		foreach ($attrs as $attr) {
			$orderedAttrs[] = $attr;
		}

		return $orderedAttrs;
	}

	private function toAttributes(array $attrs): array
	{
		$attributes = [];

		foreach ($attrs as $attrName => $attrValue) {
			$attributes[] = $this->attributeFactory->create($attrName, $attrValue);
		}

		return $attributes;
	}
}
