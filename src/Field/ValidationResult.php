<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Field\ConstraintValidationResult;
use Meraki\Schema\ValidationStatus;
use Meraki\Schema\AggregatedValidationResult;

/**
 * @extends AggregatedValidationResult<ConstraintValidationResult>
 */
final class ValidationResult extends AggregatedValidationResult
{
	public ValidationStatus $status;

	public function __construct(
		public Field $field,
		ConstraintValidationResult ...$results
	) {
		parent::__construct(...$results);

		$this->status = $this->calculateStatus();
	}

	private function calculateStatus(): ValidationStatus
	{
		if ($this->isEmpty() || $this->anyPending()) {
			return ValidationStatus::Pending;
		}

		if ($this->anyFailed()) {
			return ValidationStatus::Failed;
		}

		if ($this->allPassed()) {
			return ValidationStatus::Passed;
		}

		if ($this->allSkipped()) {
			return ValidationStatus::Skipped;
		}

		// some passed, some were skipped
		return ValidationStatus::Passed;	// maybe a ValidationStatus::Partial or something like that?
	}

	public function __clone(): void
	{
		$this->field = clone $this->field;
	}

	public function get(string $constraintName): ?ConstraintValidationResult
	{
		foreach ($this->results as $result) {
			if ((string)$result->name === $constraintName) {
				return $result;
			}
		}

		return null;
	}
}
