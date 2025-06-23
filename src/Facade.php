<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Closure;
use InvalidArgumentException;
use Meraki\Schema\Field;
use Meraki\Schema\ScopeTarget;
use Meraki\Schema\Field\Atomic;
use Meraki\Schema\Property;
use Meraki\Schema\Rule;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\Builder;
use Meraki\Schema\Deserialization\Deserializer;
use Meraki\Schema\Deserialization\JsonDeserializer;
use Meraki\Schema\Serialization\JsonSerializer;
use Meraki\Schema\Serialization\Serializer;

final class Facade implements ScopeTarget
{
	public readonly Property\Name $name;

	public function __construct(
		string $name,
		public Field\Set $fields = new Field\Set(),
		public Rule\Set $rules = new Rule\Set(),
		private readonly Field\Factory $fieldFactory = new Field\Factory(),
		// public readonly Rule\Factory $ruleFactory = new Rule\Factory(),
	) {
		$this->name = new Property\Name($name);
	}

	public static function deserialize(string $data, ?Deserializer $deserializer = null): self
	{
		return ($deserializer ?? new JsonDeserializer())->deserialize($data);
	}

	/**
	 * @template T of Field
	 * @param T $field
	 * @param Closure<T>|null $configurator
	 * @return T|self
	 */
	public function addField(Field $field, ?Closure $configurator = null): self|Field
	{
		if ($configurator !== null) {
			$configurator($field);
			$this->fields = $this->fields->add($field);

			return $this;
		}

		$this->fields = $this->fields->add($field);

		return $field;
	}

	public function addAddressField(string $name, ?Closure $configurator = null): self|Field\Address
	{
		return $this->addField($this->fieldFactory->createAddress($name), $configurator);
	}

	public function addBooleanField(string $name, ?Closure $configurator = null): self|Field\Boolean
	{
		return $this->addField($this->fieldFactory->createBoolean($name), $configurator);
	}

	public function addCreditCardField(string $name, ?Closure $configurator = null): self|Field\CreditCard
	{
		return $this->addField($this->fieldFactory->createCreditCard($name), $configurator);
	}

	public function addDateField(string $name, ?Closure $configurator = null): self|Field\Date
	{
		return $this->addField($this->fieldFactory->createDate($name), $configurator);
	}

	public function addDateTimeField(string $name, ?Closure $configurator = null): self|Field\DateTime
	{
		return $this->addField($this->fieldFactory->createDateTime($name), $configurator);
	}

	public function addDurationField(string $name, ?Closure $configurator = null): self|Field\Duration
	{
		return $this->addField($this->fieldFactory->createDuration($name), $configurator);
	}

	public function addEmailAddressField(string $name, ?Closure $configurator = null): self|Field\EmailAddress
	{
		return $this->addField($this->fieldFactory->createEmailAddress($name), $configurator);
	}

	public function addEnumField(string $name, array $options, ?Closure $configurator = null): self|Field\Enum
	{
		return $this->addField($this->fieldFactory->createEnum($name, $options), $configurator);
	}

	public function addFileField(string $name, ?Closure $configurator = null): self|Field\File
	{
		return $this->addField($this->fieldFactory->createFile($name), $configurator);
	}

	/**
	 * @param array<string, integer> $allowedCurrencies
	 */
	public function addMoneyField(string $name, array $allowedCurrencies, ?Closure $configurator = null): self|Field\Money
	{
		return $this->addField($this->fieldFactory->createMoney($name, $allowedCurrencies), $configurator);
	}

	public function addNameField(string $name, ?Closure $configurator = null): self|Field\Name
	{
		return $this->addField($this->fieldFactory->createName($name), $configurator);
	}

	public function addNumberField(string $name, ?Closure $configurator = null): self|Field\Number
	{
		return $this->addField($this->fieldFactory->createNumber($name), $configurator);
	}

	public function addPassphraseField(string $name, ?Closure $configurator = null): self|Field\Passphrase
	{
		return $this->addField($this->fieldFactory->createPassphrase($name), $configurator);
	}

	public function addPasswordField(string $name, ?Closure $configurator = null): self|Field\Password
	{
		return $this->addField($this->fieldFactory->createPassword($name), $configurator);
	}

	public function addPhoneNumberField(string $name, ?Closure $configurator = null): self|Field\PhoneNumber
	{
		return $this->addField($this->fieldFactory->createPhoneNumber($name), $configurator);
	}

	public function addTextField(string $name, ?Closure $configurator = null): self|Field\Text
	{
		return $this->addField($this->fieldFactory->createText($name), $configurator);
	}

	public function addTimeField(string $name, ?Closure $configurator = null): self|Field\Time
	{
		return $this->addField($this->fieldFactory->createTime($name), $configurator);
	}

	public function addUriField(string $name, ?Closure $configurator = null): self|Field\Uri
	{
		return $this->addField($this->fieldFactory->createUri($name), $configurator);
	}

	public function addUuidField(string $name, ?Closure $configurator = null): self|Field\Uuid
	{
		return $this->addField($this->fieldFactory->createUuid($name), $configurator);
	}

	/**
	 * @param non-empty-array<Field> $fields
	 */
	public function addVariantField(string $name, array $fields, ?Closure $configurator = null): self|Field\Variant
	{
		return $this->addField($this->fieldFactory->createVariant($name, ...$fields), $configurator);
	}

	public function input(array|object $data): self
	{
		$data = is_object($data) ? get_object_vars($data) : $data;

		// input data
		foreach ($this->fields as $field) {
			$field->input($data[(string) $field->name] ?? null);
		}

		$this->rules->apply($data, $this);

		return $this;
	}

	public function prefill(array|object $data): self
	{
		$data = is_object($data) ? get_object_vars($data) : $data;

		foreach ($this->fields as $field) {
			$field->prefill($data[(string) $field->name] ?? null);
		}

		return $this;
	}

	public function validate(array|object $data): AggregatedValidationResults
	{
		return (new SchemaValidator($this))->validate($data);
	}

	public function serialize(?Serializer $serializer = null): string
	{
		return ($serializer ?? new JsonSerializer())->serialize($this);
	}

	public function whenAllMatch(Closure $configurator): self
	{
		$this->addRule($configurator(Builder::whenAllOf()));

		return $this;
	}

	public function whenAnyMatch(Closure $configurator): self
	{
		$this->addRule($configurator(Builder::whenAnyOf()));

		return $this;
	}

	public function addRule(Rule $rule): self
	{
		if ($rule instanceof Builder) {
			$rule = $rule->build();
		}

		$this->rules = $this->rules->add($rule);

		return $this;
	}

	public function traverse(Scope $scope): ScopeResolutionResult
	{
		// If this scope points directly to the schema root
		if ($scope->isRoot()) {
			return new ScopeResolutionResult($this, $this);
		}

		$scope->rewind();
		$first = $scope->currentAsSnakeCase();

		if ($first === 'fields') {
			$scope->next();
			$fieldName = $scope->currentAsSnakeCase();

			if ($fieldName === null) {
				throw new InvalidArgumentException("Expected field name after 'fields'");
			}

			$field = $this->fields->getByName($fieldName);

			return $field->traverse($scope);
		}

		throw new InvalidArgumentException("Unsupported path segment '{$first}' at root");
	}
}
