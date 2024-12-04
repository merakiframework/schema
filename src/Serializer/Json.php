<?php
declare(strict_types=1);

namespace Meraki\Schema\Serializer;

use Meraki\Schema\Attribute;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\ConditionGroup;
use Meraki\Schema\Outcome;
use Meraki\Schema\Rule;
use Meraki\Schema\SchemaSerializer;
use Meraki\Schema\SchemaFacade;
use Meraki\Schema\Field;

final class Json implements SchemaSerializer
{
	public function serialize(SchemaFacade $schema): string
	{
		$encodedSchema = new \stdClass();
		$encodedSchema->name = $schema->name;
		$encodedSchema->fields = $this->serializeFields($schema->fields);
		$encodedSchema->rules = array_map(fn(Rule $rule): object => $this->serializeRule($rule), $schema->rules->__toArray());

		return json_encode($encodedSchema, JSON_PRETTY_PRINT);
	}

	private function serializeRule(Rule $rule): object
	{
		return (object)[
			'when' => $this->serializeConditionGroup($rule->when),
			'then' => array_map(fn(Outcome $outcome): object => $outcome->__toObject(), $rule->then)
		];
	}

	private function serializeConditionGroup(ConditionGroup $group): object
	{
		return (object)[
			'group' => $group->type,
			'conditions' => array_map(fn(Condition|ConditionGroup $condition): object => $this->serializeCondition($condition), $group->conditions),
		];
	}

	private function serializeCondition(Condition|ConditionGroup $condition): object
	{
		if ($condition instanceof ConditionGroup) {
			return $this->serializeConditionGroup($condition);
		}

		return $condition->__toObject();
	}

	private function serializeOutcomes(array $outcomes): array
	{
		$encodedOutcomes = [];

		foreach ($outcomes as $outcome) {
			$encodedOutcomes[] = $outcome->__toObject();
		}

		return $encodedOutcomes;
	}

	private function serializeFields(Field\Set $fields): array
	{
		$encodedFields = [];

		/** @var Field $field */
		foreach ($fields as $field) {
			$encodedFields[$field->name->value] = $this->serializeField($field);
		}

		return $encodedFields;
	}

	private function serializeField(Field $field): object
	{
		$encodedField = new \stdClass();

		foreach ($field->attributes as $attribute) {
			if ($attribute->name === 'name') {
				continue;
			}

			$encodedField->{$attribute->name} = $this->serializeValue($attribute->value);
		}

		return $encodedField;
	}

	private function serializeValue(mixed $value): mixed
	{
		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value) || is_float($value)) {
			return $value;
		}

		if (is_string($value)) {
			return $value;
		}

		if (is_array($value)) {
			return $this->serializeArray($value);
		}

		if (is_object($value)) {
			return $this->serializeObject($value);
		}

		return null;
	}

	private function serializeArray(array $array): array
	{
		$encodedArray = [];

		foreach ($array as $key => $value) {
			$encodedArray[$key] = $this->serializeValue($value);
		}

		return $encodedArray;
	}

	private function serializeObject(object $object): object
	{
		$encodedObject = new \stdClass();

		foreach ($object as $key => $value) {
			$encodedObject->{$key} = $this->serializeValue($value);
		}

		return $encodedObject;
	}
}
