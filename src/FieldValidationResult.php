<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\AggregatedValidationResults;

final class FieldValidationResult extends AggregatedValidationResults
{
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
