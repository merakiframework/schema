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

		$this->status = match (true) {
			$this->pending() => ValidationStatus::Pending,
			$this->failed() => ValidationStatus::Failed,
			$this->skipped() => ValidationStatus::Skipped,
			$this->passed() => ValidationStatus::Passed,
		};
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
