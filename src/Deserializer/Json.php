<?php
declare(strict_types=1);

namespace Meraki\Form\Deserializer;

use Meraki\Form\SchemaDeserializer;
use Meraki\Form\Schema;
use Meraki\Form\Field;
use Meraki\Form\Constraint;

final class Json implements SchemaDeserializer
{
	public function __construct(
		private Field\Factory $fieldFactory,
		private Constraint\Factory $constraintFactory
	) {
	}

	public function deserialize(string $serializedSchema): Schema
	{
		$schema = json_decode($serializedSchema, false, 512, \JSON_THROW_ON_ERROR);
		$fields = new Field\Set();

		if (!is_object($schema)) {
			throw new \InvalidArgumentException('Expected a JSON object: got '.gettype($schema) . '.');
		}

		$schema->fields ??= (object)[];

		foreach ($schema->fields as $field) {
			$constraints = new Constraint\Set();

			foreach ($field->constraints as $constraintName => $constraintValue) {
				$constraints = $constraints->add(
					$this->constraintFactory->create($constraintName, $constraintValue)
				);
			}

			$fields = $fields->add(
				$this->fieldFactory->create($field->name, $field->type, $constraints)
			);
		}

		return new Schema($fields);
	}
}
