<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\ValidationResult;

abstract class AggregatedValidationResults implements \IteratorAggregate, \Countable, ValidationResult
{
	public readonly int $status;

	/** @var ValidationResult[] */
	protected array $results = [];

	public function __construct(ValidationResult ...$results)
	{
		$this->results = $results;
		$this->status = $this->calculateStatus();
	}

	protected function calculateStatus(): int
	{
		if ($this->passed()) {
			return self::PASSED;
		}

		if ($this->skipped()) {
			return self::SKIPPED;
		}

		if ($this->pending()) {
			return self::PENDING;
		}

		return self::FAILED;
	}

	public function add(ValidationResult $result): self
	{
		$copy = clone $this;

		if (!$this->contains($result)) {
			$copy->results[] = $result;
		}

		return $copy;
	}

	protected function mutableAdd(ValidationResult ...$results): void
	{
		foreach ($results as $result) {
			if (!$this->contains($result)) {
				$this->results[] = $result;
			}
		}
	}

	protected function mutableRemove(ValidationResult $result): void
	{
		$this->results = array_filter($this->results, fn(ValidationResult $r): bool => $r !== $result);
	}

	public function contains(ValidationResult $result): bool
	{
		return in_array($result, $this->results, true);
	}

	public function remove(ValidationResult $result): self
	{
		return $this->filter(fn(ValidationResult $r): bool => $r !== $result);
	}

	public function getIterator(): \Traversable
	{
		return new \ArrayIterator($this->results);
	}

	public function count(): int
	{
		return count($this->results);
	}

	public function allPassed(): bool
	{
		return !$this->isEmpty() && ($this->count() === $this->getPasses()->count());
	}

	public function anyPassed(): bool
	{
		return $this->getPasses()->count() > 0;
	}

	public function allSkipped(): bool
	{
		return !$this->isEmpty() && ($this->count() === $this->getSkipped()->count());
	}

	public function anySkipped(): bool
	{
		return $this->getSkipped()->count() > 0;
	}

	public function anyPending(): bool
	{
		return $this->getPending()->count() > 0;
	}

	public function allPending(): bool
	{
		return !$this->isEmpty() && ($this->count() === $this->getPending()->count());
	}

	public function allFailed(): bool
	{
		return !$this->isEmpty() && ($this->count() === $this->getFailures()->count());
	}

	public function anyFailed(): bool
	{
		return $this->getFailures()->count() > 0;
	}

	public function getFailed(): self
	{
		return $this->filter(fn(ValidationResult $result): bool => $result->failed());
	}

	/**
	 * @deprecated @see self::getFailed()
	 */
	public function getFailures(): self
	{
		return $this->filter(fn(ValidationResult $result): bool => $result->failed());
	}

	public function getPending(): self
	{
		return $this->filter(fn(ValidationResult $result): bool => $result->pending());
	}

	public function getPassed(): self
	{
		return $this->filter(fn(ValidationResult $result): bool => $result->passed());
	}

	/**
	 * @deprecated @see self::getPassed()
	 */
	public function getPasses(): static
	{
		return $this->filter(fn(ValidationResult $result): bool => $result->passed());
	}

	public function getSkipped(): static
	{
		return $this->filter(fn(ValidationResult $result): bool => $result->skipped());
	}

	public function filter(callable $predicate): static
	{
		$copy = clone $this;
		$copy->results = array_filter($copy->results, $predicate);

		return $copy;
	}

	public function isEmpty(): bool
	{
		return $this->count() === 0;
	}

	public function merge(self $other): static
	{
		$copied = clone $this;
		$copied->results = array_merge($copied->results, $other->results);

		return $copied;
	}
}
