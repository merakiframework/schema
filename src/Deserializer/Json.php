<?php
declare(strict_types=1);

namespace Meraki\Schema\Deserializer;

use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\ConditionGroup;
use Meraki\Schema\Rule\Outcome;
use Meraki\Schema\Rule;
use Meraki\Schema\Rule\OutcomeGroup;
use Meraki\Schema\SchemaDeserializer;
use Meraki\Schema\SchemaFacade;
use Meraki\Schema\Field;
use Meraki\Schema\Attribute;
use Meraki\Schema\Scope;

final class Json implements SchemaDeserializer
{
	public function __construct(
		private Field\Factory $fieldFactory = new Field\Factory(),
		private Attribute\Factory $attributeFactory = new Attribute\Factory(),
	) {
	}

	public function deserialize(string $serializedSchema): SchemaFacade
	{
		if (is_readable($serializedSchema)) {
			$serializedSchema = file_get_contents($serializedSchema);
		}

		$schema = json_decode($serializedSchema, false, 512, \JSON_THROW_ON_ERROR);

		$this->assertIsObject($schema);

		if (!property_exists($schema, 'name')) {
			throw new \InvalidArgumentException('Schema must have a name.');
		}

		$this->assertPropertyIsString($schema, 'name');

		return new SchemaFacade(
			$schema->name,
			$this->deserializeFields($schema),
			$this->deserializeRules($schema),
		);
	}

	private function assertPropertyIsString(object $object, string $propertyName): void
	{
		if (!is_string($object->$propertyName)) {
			throw new \InvalidArgumentException('Expected "'.$propertyName.'" property to be a string: got '.gettype($object->$propertyName) . '.');
		}
	}

	private function deserializeRules(object $schema): Rule\Set
	{
		if (!property_exists($schema, 'rules')) {
			return new Rule\Set();
		}

		$this->assertPropertyIsArray($schema, 'rules');

		$rules = new Rule\Set();

		foreach ($schema->rules as $ruleDefinition) {
			$rules = $rules->add($this->deserializeRule($ruleDefinition));
		}

		return $rules;
	}

	private function deserializeRule(object $ruleDefinition): Rule
	{
		$this->assertPropertyExists($ruleDefinition, 'when');
		$this->assertPropertyIsObject($ruleDefinition, 'when');

		return new Rule($this->decodeConditionGroup($ruleDefinition->when), $this->deserializeOutcomes($ruleDefinition));
	}

	private function deserializeOutcomes(object $ruleDefinition): OutcomeGroup
	{
		$this->assertPropertyExists($ruleDefinition, 'then');
		$this->assertPropertyIsArray($ruleDefinition, 'then');

		$decodedOutcomes = new OutcomeGroup();

		foreach ($ruleDefinition->then as $outcome) {
			$this->assertIsObject($outcome);
			$this->assertPropertyExists($outcome, 'target');
			$this->assertPropertyExists($outcome, 'action');

			$target = new Attribute('target', $outcome->target);
			$action = new Attribute('action', $outcome->action);
			$attrs = $this->deserializeAttributes($outcome, ['target', 'action']);
			$decodedOutcomes = $decodedOutcomes->add(new Outcome($action, $target, ...$attrs));
		}

		return $decodedOutcomes;
	}

	private function decodeConditionGroups(object $conditionGroup): ConditionGroup
	{
		$this->assertPropertyExists($conditionGroup, 'group');
		$this->assertPropertyExists($conditionGroup, 'conditions');
		$this->assertPropertyIsArray($conditionGroup, 'conditions');

		return new ConditionGroup($conditionGroup->group, ...$this->deserializeConditions($conditionGroup->conditions));
	}

	private function decodeCondition(object $conditionsOrGroup): Condition
	{
		$this->assertPropertyExists($conditionsOrGroup, 'target');
		$this->assertPropertyExists($conditionsOrGroup, 'operator');
		$this->assertPropertyExists($conditionsOrGroup, 'expected');

		return new Condition(
			new Attribute('target', $conditionsOrGroup->target),
			new Attribute('operator', $conditionsOrGroup->operator),
			new Attribute('expected', $conditionsOrGroup->expected),
		);
	}

	private function deserializeConditions(array $conditions): array
	{
		$decodedConditions = [];

		foreach ($conditions as $conditionOrGroup) {
			$this->assertIsObject($conditionOrGroup);

			if (property_exists($conditionOrGroup, 'target')) {
				$decodedConditions[] = $this->decodeCondition($conditionOrGroup);
			} else {
				$decodedConditions[] = $this->decodeConditionGroup($conditionOrGroup);
			}
		}

		return $decodedConditions;
	}

	private function decodeConditionGroup(object $conditionGroup): ConditionGroup
	{
		$this->assertPropertyExists($conditionGroup, 'group');
		$this->assertPropertyExists($conditionGroup, 'conditions');
		$this->assertPropertyIsArray($conditionGroup, 'conditions');

		return new ConditionGroup($conditionGroup->group, ...$this->deserializeConditions($conditionGroup->conditions));
	}

	private function deserializeFields(object $schema): Field\Set
	{
		$this->assertPropertyExists($schema, 'fields');
		$this->assertPropertyIsObject($schema, 'fields');

		$fields = new Field\Set();

		foreach ($schema->fields as $fieldName => $fieldDefinition) {
			$this->assertIsObject($fieldDefinition);
			$this->assertPropertyExists($fieldDefinition, 'type');

			$attrs = (array)$fieldDefinition;

			unset($attrs['type']);

			$fields = $fields->add($this->fieldFactory->create($fieldDefinition->type, $fieldName, $attrs));
		}

		return $fields;
	}

	private function deserializeAttributes(object $fieldDefinition, array $skip = []): array
	{
		$attributes = [];

		foreach ((array)$fieldDefinition as $attrName => $attrValue) {
			if (in_array($attrName, $skip, true)) {
				continue;
			}

			$attributes[] = new Attribute($attrName, $attrValue);
		}

		return $attributes;
	}

	private function assertPropertyExists(object $object, string $propertyName): void
	{
		if (!property_exists($object, $propertyName)) {
			throw new \InvalidArgumentException('Expected a property named "'.$propertyName.'" in object.');
		}
	}

	private function assertPropertyIsArray(object $object, string $propertyName): void
	{
		if (!is_array($object->$propertyName)) {
			throw new \InvalidArgumentException('Expected "'.$propertyName.'" property to be an array: got '.gettype($object->$propertyName) . '.');
		}
	}

	private function assertPropertyIsObject(object $object, string $propertyName): void
	{
		if (!is_object($object->$propertyName)) {
			throw new \InvalidArgumentException('Expected "'.$propertyName.'" property to be an object: got '.gettype($object->$propertyName) . '.');
		}
	}

	private function assertIsObject(mixed $value): void
	{
		if (!is_object($value)) {
			throw new \InvalidArgumentException('Expected an object: got '.gettype($value) . '.');
		}
	}
}
