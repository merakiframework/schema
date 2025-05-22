<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\ValidationResult;
use IteratorAggregate;
use Countable;

/**
 * @template T of ValidationResult
 * @extends IteratorAggregate<int, T>
 */
abstract class AggregatedValidationResult implements IteratorAggregate, Countable, ValidationResult
{
	/**
	 * @var list<T> $results
	 * @readonly
	 */
	public array $results;

	/**
	 * @param T ...$results
	 */
	public function __construct(ValidationResult ...$results)
	{
		$this->results = $results;
	}

	/**
	 * @param T $result
	 */
	public function add(ValidationResult $result): static
	{
		$self = clone $this;
		$self->results[] = $result;

		return $self;
	}

	/**
	 * @param T $result
	 */
	public function remove(ValidationResult $result): static
	{
		$self = clone $this;
		$self->results = array_filter($this->results, fn(ValidationResult $r): bool => $r !== $result);

		return $self;
	}

	/**
	 * @param T $result
	 */
	public function contains(ValidationResult $result): bool
	{
		return in_array($result, $this->results, true);
	}

	public function allPassed(): bool
	{
		return $this->count() === $this->getPassed()->count();
	}

	public function anyPassed(): bool
	{
		return $this->getPassed()->isNotEmpty();
	}

	public function allSkipped(): bool
	{
		return $this->count() === $this->getSkipped()->count();
	}

	public function anySkipped(): bool
	{
		return $this->getSkipped()->isNotEmpty();
	}

	public function allPending(): bool
	{
		return $this->count() === $this->getPending()->count();
	}

	public function anyPending(): bool
	{
		return $this->getPending()->isNotEmpty();
	}

	public function allFailed(): bool
	{
		return $this->count() === $this->getFailed()->count();
	}
	public function anyFailed(): bool
	{
		return $this->getFailed()->isNotEmpty();
	}

	public function getFailed(): static
	{
		return $this->filter(fn(ValidationResult $result): bool => $result->status === ValidationStatus::Failed);
	}

	public function getPending(): static
	{
		return $this->filter(fn(ValidationResult $result): bool => $result->status === ValidationStatus::Pending);
	}

	public function getPassed(): static
	{
		return $this->filter(fn(ValidationResult $result): bool => $result->status === ValidationStatus::Passed);
	}

	public function getSkipped(): static
	{
		return $this->filter(fn(ValidationResult $result): bool => $result->status === ValidationStatus::Skipped);
	}

	/**
	 * @param callable(T): bool $predicate
	 */
	public function filter(callable $predicate): static
	{
		$self = clone $this;
		$self->results = array_filter($this->results, $predicate);

		return $self;
	}

	public function isEmpty(): bool
	{
		return $this->count() === 0;
	}

	public function isNotEmpty(): bool
	{
		return $this->count() > 0;
	}

	/**
	 * @return T|null
	 */
	public function getFirst(): ?ValidationResult
	{
		return $this->results[0] ?? null;
	}

	/**
	 * @return T|null
	 */
	public function getLast(): ?ValidationResult
	{
		return $this->results[count($this->results) - 1] ?? null;
	}

	/**
	 * @template A of AggregatedValidationResult<T>
	 * @param A $other
	 */
	public function merge(self $other): static
	{
		$self = clone $this;
		$self->results = array_merge($this->results, $other->results);

		return $self;
	}

	public function count(): int
	{
		return count($this->results);
	}

	/**
	 * @return \ArrayIterator<T>
	 */
	public function getIterator(): \ArrayIterator
	{
		return new \ArrayIterator($this->results);
	}
}
