<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\ValidationResult;

final class FieldValidationResult implements ValidationResult
{
	public readonly int $status;

	public function __construct(
		public readonly Field $field,
		public readonly FieldValueValidationResult $valueValidationResult,
		public readonly AggregatedConstraintValidationResults $constraintValidationResults,
	) {
		$this->status = $this->calculateStatus();
	}

	public function failed(): bool
	{
		return $this->valueValidationResult->failed() || $this->constraintValidationResults->failed();
	}

	public function pending(): bool
	{
		return $this->valueValidationResult->pending() || $this->constraintValidationResults->pending();
	}

	public function passed(): bool
	{
		return $this->valueValidationResult->passed()
			&& $this->constraintValidationResults->passed();
	}

	public function skipped(): bool
	{
		return $this->valueValidationResult->skipped() && $this->constraintValidationResults->skipped();
	}

	private function calculateStatus(): int
	{
		if ($this->failed()) {
			return self::FAILED;
		}

		if ($this->skipped()) {
			return self::SKIPPED;
		}

		return self::PASSED;
	}
}
