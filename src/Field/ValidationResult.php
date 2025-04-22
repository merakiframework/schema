<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\ValidationResult as ValidationResultInterface;
use Meraki\Schema\ValidationStatus;
use Meraki\Schema\Validator\ValidationResult as ValidatorValidationResult;
use Meraki\Schema\AggregatedValidationResult;

/**
 * @implements AggregatedValidationResult<ValidatorValidationResult>
 */
final class ValidationResult implements AggregatedValidationResult
{
	/** @var list<ValidatorValidationResult> $results */
	public readonly array $results;

	public readonly ValidationStatus $status;

	public function __construct(
		public readonly Field $field,
		ValidatorValidationResult ...$results
	) {
		$this->results = $results;
		$this->status = $this->calculateStatus();
	}

	public function add(ValidationResultInterface $result): static
	{
		return new self(
			$this->field,
			...array_merge($this->results, [$result])
		);
	}

	public function remove(ValidationResultInterface $result): static
	{
		return new self(
			$this->field,
			...array_filter($this->results, fn(ValidatorValidationResult $r): bool => $r !== $result)
		);
	}

	public function contains(ValidationResultInterface $result): bool
	{
		return in_array($result, $this->results, true);
	}

	private function calculateStatus(): ValidationStatus
	{
		if ($this->isEmpty() || $this->anyPending()) {
			return ValidationStatus::Pending;
		}

		if ($this->allFailed()) {
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

	public function getFailed(): static
	{
		return $this->filter(fn(ValidatorValidationResult $result): bool => $result->status === ValidationStatus::Failed);
	}

	public function getPassed(): static
	{
		return $this->filter(fn(ValidatorValidationResult $result): bool => $result->status === ValidationStatus::Passed);
	}

	public function getSkipped(): static
	{
		return $this->filter(fn(ValidatorValidationResult $result): bool => $result->status === ValidationStatus::Skipped);
	}

	public function getPending(): static
	{
		return $this->filter(fn(ValidatorValidationResult $result): bool => $result->status === ValidationStatus::Pending);
	}

	public function filter(callable $predicate): static
	{
		return new self($this->field, ...array_filter($this->results, $predicate));
	}

	public function count(): int
	{
		return count($this->results);
	}
	public function isEmpty(): bool
	{
		return empty($this->results);
	}

	public function isNotEmpty(): bool
	{
		return !$this->isEmpty();
	}

	public function getFirst(): ?ValidatorValidationResult
	{
		return $this->results[0] ?? null;
	}

	public function getIterator(): \ArrayIterator
	{
		return new \ArrayIterator($this->results);
	}

	public function merge(AggregatedValidationResult $other): static
	{
		return new self(
			$this->field,
			...array_merge($this->results, $other->results)
		);
	}

	public function allPassed(): bool
	{
		foreach ($this->results as $result) {
			if ($result->status !== ValidationStatus::Passed) {
				return false;
			}
		}

		return true;
	}

	public function anyPassed(): bool
	{
		foreach ($this->results as $result) {
			if ($result->status === ValidationStatus::Passed) {
				return true;
			}
		}

		return false;
	}

	public function allSkipped(): bool
	{
		foreach ($this->results as $result) {
			if ($result->status !== ValidationStatus::Skipped) {
				return false;
			}
		}

		return true;
	}

	public function anySkipped(): bool
	{
		foreach ($this->results as $result) {
			if ($result->status === ValidationStatus::Skipped) {
				return true;
			}
		}

		return false;
	}

	public function allPending(): bool
	{
		foreach ($this->results as $result) {
			if ($result->status !== ValidationStatus::Pending) {
				return false;
			}
		}

		return true;
	}

	public function anyPending(): bool
	{
		foreach ($this->results as $result) {
			if ($result->status === ValidationStatus::Pending) {
				return true;
			}
		}

		return false;
	}

	public function allFailed(): bool
	{
		foreach ($this->results as $result) {
			if ($result->status !== ValidationStatus::Failed) {
				return false;
			}
		}

		return true;
	}

	public function anyFailed(): bool
	{
		foreach ($this->results as $result) {
			if ($result->status === ValidationStatus::Failed) {
				return true;
			}
		}

		return false;
	}
}
