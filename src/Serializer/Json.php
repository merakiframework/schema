<?php
declare(strict_types=1);

namespace Meraki\Form\Serializer;

use Meraki\Form\NameInflector;
use Meraki\Form\SchemaSerializer;
use Meraki\Form\Schema;
use Meraki\Form\Field;
use Meraki\Form\Constraint;

final class Json implements SchemaSerializer
{
	private NameInflector $nameInflector;

	public function __construct()
	{
		$this->nameInflector = new NameInflector();
	}

	public function serialize(Schema $schema): string
	{
		return $this->encodeSchema($schema);
	}

	private function encodeSchema(Schema $schema): string
	{
		$encodedSchema = new \stdClass();
		$encodedSchema->fields = $this->encodeFields($schema->fields);

		return json_encode($encodedSchema, JSON_PRETTY_PRINT);
	}

	private function encodeFields(Field\Set $fields): array
	{
		$encodedFields = [];

		/** @var Field $field */
		foreach ($fields as $field) {
			$encodedFields[] = $this->encodeField($field);
		}

		return $encodedFields;
	}

	private function encodeField(Field $field): object
	{
		$encodedField = new \stdClass();
		$encodedField->name = $field->name;
		$encodedField->type = $field->type;
		$encodedField->constraints = $this->encodeConstraints($field->constraints);

		return $encodedField;
	}

	private function encodeConstraints(Constraint\Set $constraints): object
	{
		$encodedConstraints = new \stdClass();

		/** @var Constraint $constraint */
		foreach ($constraints as $constraint) {
			$name = $this->nameInflector->inflectOn($constraint::class);
			$encodedConstraints->{$name} = $this->encodeValue($constraint->value);
		}

		return $encodedConstraints;
	}

	private function encodeValue(mixed $value): mixed
	{
		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value)) {
			return $value;
		}

		if (is_string($value)) {
			return $value;
		}

		if (is_array($value)) {
			return $this->encodeArray($value);
		}

		if (is_object($value)) {
			return $this->encodeObject($value);
		}

		return null;
	}

	private function encodeArray(array $array): array
	{
		$encodedArray = [];

		foreach ($array as $key => $value) {
			$encodedArray[$key] = $this->encodeValue($value);
		}

		return $encodedArray;
	}

	private function encodeObject(object $object): object
	{
		$encodedObject = new \stdClass();

		foreach ($object as $key => $value) {
			$encodedObject->{$key} = $this->encodeValue($value);
		}

		return $encodedObject;
	}
}
