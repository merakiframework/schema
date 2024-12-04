<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\AggregatedValidationResults;
use Meraki\Schema\SchemaFacade;
use Meraki\Schema\Rule\ConditionGroup;
use Meraki\Schema\Outcome;
use Meraki\Schema\SchemaValidationResult;
use Meraki\Schema\Field;
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

		// evaluate rules
		foreach ($this->schema->rules as $rule) {
			if ($rule->evaluate($data, $this->schema)) {
				foreach ($rule->then as $outcome) {
					$this->applyOutcome($outcome, $results, $data, $this->schema);
				}
			}
		}

		// validate fields
		foreach ($this->schema->fields as $field) {
			$fieldResult = $field->validate();
			$results = $results->add($fieldResult);
		}

		return $results;
	}

	private function applyOutcome(
		Outcome $outcome,
		AggregatedFieldValidationResults $validationResult,
		array $data,
		SchemaFacade $schema,
	): void {
		$target = $outcome->target;
		$action = $outcome->action;

		switch ($action->value) {
			case 'require':
				$target = $target->getScope()->resolve($schema);
				if (!($target instanceof Field)) {
					throw new \RuntimeException('Cannot require non-field');
				}
				$target->require();
				break;

			case 'set':
				if (!isset($outcome->to)) {
					throw new \RuntimeException('Missing "to" value for "set" action');
				}
				$scope = $target->getScope();
				$target = $scope->resolveWithBackTracking($schema);
				if (!($target instanceof Field)) {
					throw new \RuntimeException('Cannot set attribute on non-field');
				}
				$attr = $this->attributeFactory->create($scope->getLastSegment(), $outcome->to->value);
				$target->setAttribute($attr);
				break;

			case 'replace':
				if (!isset($outcome->with)) {
					throw new \RuntimeException('Missing "with" value for "replace" action');
				}
				$scope = $target->getScope();
				$target = $scope->resolve($schema);
				throw new \RuntimeException('Not implemented yet');
				// break;

			default:
				throw new \RuntimeException('Unknown action: ' . $action->value);
		}
	}
}
