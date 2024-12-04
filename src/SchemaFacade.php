<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Field;
use Meraki\Schema\Rule;
use Meraki\Schema\AggregatedValidationResults;

final class SchemaFacade
{
	public Field\Factory $fieldFactory;

	public function __construct(
		public string $name,
		public Field\Set $fields = new Field\Set(),
		public Rule\Set $rules = new Rule\Set(),
	) {
		$this->fieldFactory = new Field\Factory();
	}

	public static function deserialize(string $data, ?SchemaDeserializer $deserializer = null): self
	{
		return ($deserializer ?? new Deserializer\Json())->deserialize($data);
	}

	public function addField(Field $field): self
	{
		$this->fields = $this->fields->add($field);

		return $this;
	}

	public function addAddressField(string $name): Field\Address
	{
		$addressField = $this->fieldFactory->createAddress($name);
		$this->fields = $this->fields->add($addressField);

		return $addressField;
	}

	public function addBooleanField(string $name): Field\Boolean
	{
		$booleanField = $this->fieldFactory->createBoolean($name);
		$this->fields = $this->fields->add($booleanField);

		return $booleanField;
	}

	public function addCreditCardField(string $name): Field\CreditCard
	{
		$creditCardField = $this->fieldFactory->createCreditCard($name);
		$this->fields = $this->fields->add($creditCardField);

		return $creditCardField;
	}

	public function addDateField(string $name): Field\Date
	{
		$dateField = $this->fieldFactory->createDate($name);
		$this->fields = $this->fields->add($dateField);

		return $dateField;
	}

	public function addDateTimeField(string $name): Field\DateTime
	{
		$dateTimeField = $this->fieldFactory->createDateTime($name);
		$this->fields = $this->fields->add($dateTimeField);

		return $dateTimeField;
	}

	public function addDurationField(string $name): Field\Duration
	{
		$durationField = $this->fieldFactory->createDuration($name);
		$this->fields = $this->fields->add($durationField);

		return $durationField;
	}

	public function addEmailAddressField(string $name): Field\EmailAddress
	{
		$emailAddressField = $this->fieldFactory->createEmailAddress($name);
		$this->fields = $this->fields->add($emailAddressField);

		return $emailAddressField;
	}

	public function addEnumField(string $name, array $options): Field\Enum
	{
		$enumField = $this->fieldFactory->createEnum($name, $options);
		$this->fields = $this->fields->add($enumField);

		return $enumField;
	}

	public function addFileField(string $name): Field\File
	{
		$fileField = $this->fieldFactory->createFile($name);
		$this->fields = $this->fields->add($fileField);

		return $fileField;
	}

	public function addMoneyField(string $name): Field\Money
	{
		$moneyField = $this->fieldFactory->createMoney($name);
		$this->fields = $this->fields->add($moneyField);

		return $moneyField;
	}

	public function addNameField(string $name): Field\Name
	{
		$nameField = $this->fieldFactory->createName($name);
		$this->fields = $this->fields->add($nameField);

		return $nameField;
	}

	public function addNumberField(string $name): Field\Number
	{
		$numberField = $this->fieldFactory->createNumber($name);
		$this->fields = $this->fields->add($numberField);

		return $numberField;
	}

	public function addPassphraseField(string $name): Field\Passphrase
	{
		$passphraseField = $this->fieldFactory->createPassphrase($name);
		$this->fields = $this->fields->add($passphraseField);

		return $passphraseField;
	}

	public function addPasswordField(string $name): Field\Password
	{
		$passwordField = $this->fieldFactory->createPassword($name);
		$this->fields = $this->fields->add($passwordField);

		return $passwordField;
	}

	public function addPhoneNumberField(string $name): Field\PhoneNumber
	{
		$phoneNumberField = $this->fieldFactory->createPhoneNumber($name);
		$this->fields = $this->fields->add($phoneNumberField);

		return $phoneNumberField;
	}

	public function addTextField(string $name): Field\Text
	{
		$textField = $this->fieldFactory->createText($name);
		$this->fields = $this->fields->add($textField);

		return $textField;
	}

	public function addTimeField(string $name): Field\Time
	{
		$timeField = $this->fieldFactory->createTime($name);
		$this->fields = $this->fields->add($timeField);

		return $timeField;
	}

	public function addUrlField(string $name): Field\Url
	{
		$urlField = $this->fieldFactory->createUrl($name);
		$this->fields = $this->fields->add($urlField);

		return $urlField;
	}

	public function addUuidField(string $name): Field\Uuid
	{
		$uuidField = $this->fieldFactory->createUuid($name);
		$this->fields = $this->fields->add($uuidField);

		return $uuidField;
	}

	public function validate(array|object $data): AggregatedValidationResults
	{
		return (new SchemaValidator($this))->validate($data);
	}

	public function serialize(?SchemaSerializer $serializer = null): string
	{
		return ($serializer ?? new Serializer\Json())->serialize($this);
	}

	public function addRule(Rule|Rule\Builder $rule): self
	{
		if ($rule instanceof Rule\Builder) {
			$rule = $rule->build();
		}

		$this->rules = $this->rules->add($rule);

		return $this;
	}
}
