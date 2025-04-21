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
interface AggregatedValidationResult extends IteratorAggregate, Countable, ValidationResult
{
	/**
	 * @param T $result
	 */
	public function add(ValidationResult $result): static;

	/**
	 * @param T $result
	 */
	public function remove(ValidationResult $result): static;

	/**
	 * @param T $result
	 */
	public function contains(ValidationResult $result): bool;

	public function allPassed(): bool;
	public function anyPassed(): bool;
	public function allSkipped(): bool;
	public function anySkipped(): bool;
	public function allPending(): bool;
	public function anyPending(): bool;
	public function allFailed(): bool;
	public function anyFailed(): bool;
	public function getFailed(): static;
	public function getPending(): static;
	public function getPassed(): static;
	public function getSkipped(): static;

	/**
	 * @param callable(T): bool $predicate
	 */
	public function filter(callable $predicate): static;
	public function isEmpty(): bool;
	public function isNotEmpty(): bool;

	/**
	 * @return T|null
	 */
	public function getFirst(): ?ValidationResult;

	/**
	 * @template A of AggregatedValidationResult<T>
	 * @param A $other
	 */
	public function merge(self $other): static;
}
