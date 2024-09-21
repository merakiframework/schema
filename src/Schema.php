<?php
declare(strict_types=1);

namespace Meraki\Form;

use Meraki\Form\Field;

class Schema
{
	public function __construct(
		public Field\Set $fields = new Field\Set()
	) {
	}

	public function add(Field $field): self
	{
		$this->fields = $this->fields->add($field);

		return $this;
	}

	public function validate(array|object $data): ValidationResult
	{
		$data = is_object($data) ? get_object_vars($data) : $data;
		$validationResult = new ValidationResult($this);

		foreach ($this->fields as $field) {
			$validationResult->addFieldResult($field->validate($data[$field->name] ?? null));
		}

		return $validationResult;
	}

	public function serialize(SchemaSerializer $serializer): string
	{
		return $serializer->serialize($this);
	}
}
