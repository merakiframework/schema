<?php
declare(strict_types=1);

namespace Meraki\Schema\Deserialization;

use Meraki\Schema\Deserialization\Deserializer;
use Meraki\Schema\Rule\ConditionFactory;
use Meraki\Schema\Rule\OutcomeFactory;
use Meraki\Schema\Rule;
use Meraki\Schema\Facade;
use Meraki\Schema\Field;
use Meraki\Schema\Property;
use InvalidArgumentException;

final class JsonDeserializer implements Deserializer
{
	public function __construct(
		private Field\Factory $fieldFactory = new Field\Factory(),
		private Property\Factory $attributeFactory = new Property\Factory(),
		private ConditionFactory $conditionFactory = new ConditionFactory(),
		private OutcomeFactory $outcomeFactory = new OutcomeFactory(),
	) {
	}

	public function deserialize(string $serializedSchema): Facade
	{
		if (is_readable($serializedSchema)) {
			$serializedSchema = file_get_contents($serializedSchema);
		}

		$schema = json_decode($serializedSchema, false, 512, \JSON_THROW_ON_ERROR);

		$this->assertIsObject($schema);
		$this->assertPropertyExists($schema, 'name');

		if (!is_string($schema->name)) {
			throw new InvalidArgumentException('Expected "name" property to be a string: got ' . gettype($schema->name) . '.');
		}

		return new Facade(
			$schema->name,
			$this->deserializeFields($schema),
			$this->deserializeRules($schema),
		);
	}

	private function deserializeFields(object $schema): Field\Set
	{
		$this->assertPropertyExists($schema, 'fields');
		$this->assertIsObject($schema->fields);

		$fields = new Field\Set();

		foreach ($schema->fields as $fieldName => $serializedField) {
			$this->assertIsObject($serializedField);
			$this->assertPropertyExists($serializedField, 'type');

			$fields = $fields->add($this->fieldFactory->deserialize($serializedField));
		}

		return $fields;
	}

	private function deserializeRules(object $schema): Rule\Set
	{
		$this->assertPropertyExists($schema, 'rules');

		if (!is_array($schema->rules)) {
			throw new InvalidArgumentException('Expected "rules" property to be an array: got ' . gettype($schema->rules) . '.');
		}

		$rules = new Rule\Set();

		foreach ($schema->rules as $serializedRule) {
			$rules = $rules->add(Rule::deserialize(
				$serializedRule,
				$this->conditionFactory,
				$this->outcomeFactory,
			));
		}

		return $rules;
	}

	private function assertPropertyExists(object $object, string $propertyName): void
	{
		if (!property_exists($object, $propertyName)) {
			throw new InvalidArgumentException('Expected a property named "'.$propertyName.'" in object.');
		}
	}

	private function assertIsObject(mixed $value): void
	{
		if (!is_object($value)) {
			throw new InvalidArgumentException('Expected an object: got '.gettype($value) . '.');
		}
	}
}
