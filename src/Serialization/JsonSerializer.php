<?php
declare(strict_types=1);

namespace Meraki\Schema\Serialization;

use Meraki\Schema\Serialization\Serializer;
use Meraki\Schema\Rule;
use Meraki\Schema\Facade;
use Meraki\Schema\Field;

final class JsonSerializer implements Serializer
{
	public function serialize(Facade $schema): string
	{
		$serializedSchema = [
			'name' => (string)$schema->name,
			'fields' => $this->serializeFields($schema->fields),
			'rules' => $this->serializeRules($schema->rules),
		];

		return json_encode((object)$serializedSchema, JSON_PRETTY_PRINT);
	}

	/**
	 * @phpstan-import-type SerializedField from Field
	 * @return SerializedField[]
	 */
	private function serializeFields(Field\Set $fields): array
	{
		$serializedField = [];

		foreach ($fields as $field) {
			$field = $field->serialize();
			$serializedField[$field->name] = $field;
		}

		return $serializedField;
	}

	/**
	 * @phpstan-import-type SerializedRule from Rule
	 * @return SerializedRule[]
	 */
	private function serializeRules(Rule\Set $rules): array
	{
		$serializedRules = [];

		foreach ($rules as $rule) {
			if ($rule instanceof Rule\Builder) {
				$rule = $rule->build();
			}

			$serializedRules[] = $rule->serialize();
		}

		return $serializedRules;
	}
}
