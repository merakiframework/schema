<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\ValidationResult;
use Meraki\Schema\AggregatedValidationResult;

final class SchemaValidationResult extends AggregatedValidationResult
{
	public readonly ValidationStatus $status;

	public function __construct(ValidationResult ...$results)
	{
		parent::__construct(...$results);

		$this->status = $this->calculateStatus();
	}

	private function calculateStatus(): ValidationStatus
	{
		if ($this->pending()) {
			return ValidationStatus::Pending;
		}

		if ($this->failed()) {
			return ValidationStatus::Failed;
		}

		if ($this->skipped()) {
			return ValidationStatus::Skipped;
		}

		// all passed, or a mix of passed and skipped
		return ValidationStatus::Passed;
	}

	public function failed(): bool
	{
		return $this->anyFailed();
	}

	public function passed(): bool
	{
		return $this->allPassed();
	}

	public function skipped(): bool
	{
		return $this->allSkipped();
	}

	public function pending(): bool
	{
		return $this->isEmpty() || $this->anyPending();
	}
}
