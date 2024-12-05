<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\SchemaFacade;
use Meraki\Schema\Attribute;

final class SchemaValidator
{
	private SchemaFacade $schema;

	private Attribute\Factory $attributeFactory;

	public function __construct(SchemaFacade $schema)
	{
		$this->schema = $schema;
		$this->attributeFactory = new Attribute\Factory();
	}

	public function validate(array|object $data): AggregatedFieldValidationResults
	{
		$data = is_object($data) ? get_object_vars($data) : $data;
		$results = new AggregatedFieldValidationResults();

		// input data
		foreach ($this->schema->fields as $field) {
			$defaultValue = $field->defaultValue;
			$fieldResult = $field->input($data[(string)$field->name] ?? null);
		}

		$this->schema->rules->apply($data, $this->schema);

		// validate fields
		foreach ($this->schema->fields as $field) {
			$fieldResult = $field->validate();
			$results = $results->add($fieldResult);
		}

		return $results;
	}
}
