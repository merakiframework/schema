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
	public function __construct(
		public Composite|Variant $composite,
		FieldValidationResult ...$fieldResults
	) {
		parent::__construct(...$fieldResults);
	}

	protected function calculateStatus(): ValidationStatus
	{
		if ($this->isEmpty() || $this->anyPending()) {
			return ValidationStatus::Pending;
		}

		if ($this->anyFailed()) {
			return ValidationStatus::Failed;
		}

		if ($this->allSkipped()) {
			return ValidationStatus::Skipped;
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
