<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\ValidationResult as FieldValidationResult;
use Meraki\Schema\ValidationStatus;
use Meraki\Schema\AggregatedValidationResult;

/**
 * @extends AggregatedValidationResult<FieldValidationResult>
 */
final class CompositeValidationResult extends AggregatedValidationResult
{
	public readonly ValidationStatus $status;

	public function __construct(
		public Composite|Variant $composite,
		FieldValidationResult ...$fieldResults
	) {
		parent::__construct(...$fieldResults);

		$this->status = $this->calculateStatus();
	}

	private function calculateStatus(): ValidationStatus
	{
		if ($this->isEmpty() || $this->anyPending()) {
			return ValidationStatus::Pending;
		}

		if ($this->allFailed()) {
			return ValidationStatus::Failed;
		}

		return ValidationStatus::Passed;
	}

	public function get(string $fieldName): ?FieldValidationResult
	{
		foreach ($this->results as $result) {
			if ((string)$result->field->name === $fieldName) {
				return $result;
			}
		}

		return null;
	}

	public function __clone(): void
	{
		$this->composite = clone $this->composite;
	}
}
