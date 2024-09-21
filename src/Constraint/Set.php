<?php
declare(strict_types=1);

namespace Meraki\Form\Constraint;

use Meraki\Form\Constraint;
use Meraki\Form\NameInflector;

/**
 * A set of constraints. This class is immutable.
 */
final class Set implements \IteratorAggregate, \Countable
{
	/** @var Constraint[] */
	private array $constraints = [];

	private NameInflector $nameInflector;

	/**
	 * Create a new set of constraints.
	 */
	public function __construct(Constraint ...$constraints)
	{
		$this->nameInflector = new NameInflector();

		// Ensure that no constraints are duplicated.
		foreach ($constraints as $constraint) {
			if (!$this->contains($constraint)) {
				$this->constraints[] = $constraint;
			}
		}
	}

	/**
	 * Add one or more constraints to the set if they are not already present.
	 */
	public function add(Constraint $constraint, Constraint ...$constraints): self
	{
		$constraints = array_merge([$constraint], $constraints);
		$copy = clone $this;

		foreach ($constraints as $constraint) {
			if (!$copy->contains($constraint)) {
				$copy->constraints[] = $constraint;
			}
		}

		return $copy;
	}

	/**
	 * Find all constraints that pass the callback.
	 */
	public function find(callable $criteria): self
	{
		$set = new self();

		foreach ($this->constraints as $constraint) {
			if ($criteria($constraint)) {
				$set = $set->add($constraint);
			}
		}

		return $set;
	}

	/**
	 * Get the first constraint in the set.
	 */
	public function first(): ?Constraint
	{
		return $this->constraints[0] ?? null;
	}

	/**
	 * Get the last constraint in the set.
	 */
	public function last(): ?Constraint
	{
		return $this->constraints[count($this->constraints) - 1] ?? null;
	}

	/**
	 * Replace one or more constraints in the set, removing existing constraints that match.
	 */
	public function replace(Constraint $constraint, Constraint ...$constraints): self
	{
		$constraints = array_merge([$constraint], $constraints);

		return (clone $this)->remove(...$constraints)->add(...$constraints);
	}

	/**
	 * Merge another set of constraints into this set.
	 */
	public function merge(self $other): self
	{
		return (clone $this)->add(...$other->constraints);
	}

	/**
	 * Check if the set is empty.
	 */
	public function isEmpty(): bool
	{
		return count($this->constraints) === 0;
	}

	/**
	 * Remove one or more constraints from the set if they are present.
	 */
	public function remove(Constraint $constraint, Constraint ...$constraints): self
	{
		$constraints = array_merge([$constraint], $constraints);
		$copy = clone $this;

		foreach ($constraints as $constraint) {
			$index = $copy->indexOf($constraint);

			if ($index !== null) {
				unset($copy->constraints[$index]);
			}
		}

		return $copy;
	}

	/**
	 * Find a constraint by its name.
	 */
	public function findByName(string $name): ?Constraint
	{
		return $this->find(fn(Constraint $constraint): bool => $this->nameInflector->inflectOn($constraint::class) === $name)->first();
	}

	/**
	 * Check if or more constraints are present in the set.
	 */
	public function contains(Constraint $constraint, Constraint ...$constraints): bool
	{
		$constraints = array_merge([$constraint], $constraints);

		foreach ($constraints as $constraint) {
			if ($this->indexOf($constraint) === null) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Return the index of a constraint in the set.
	 */
	public function indexOf(Constraint $constraint): ?int
	{
		foreach ($this->constraints as $index => $item) {
			if ($this->nameInflector->inflectOn($item::class) === $this->nameInflector->inflectOn($constraint::class)) {
				return $index;
			}
		}

		return null;
	}

	/**
	 * Return a new set containing only the constraints that pass the callback.
	 */
	public function filter(callable $callback): self
	{
		$set = new self();

		foreach ($this->constraints as $constraint) {
			if ($callback($constraint)) {
				$set->add($constraint);
			}
		}

		return $set;
	}

	public function validate(mixed $value): array
	{
		$results = [];

		foreach ($this->constraints as $constraint) {
			$results[] = $constraint->validate($value);
		}

		return $results;
	}

	/**
	 * Return an iterator for the constraints in the set.
	 */
	public function getIterator(): \ArrayIterator
	{
		return new \ArrayIterator($this->constraints);
	}

	/**
	 * Count the number of constraints in the set.
	 */
	public function count(): int
	{
		return count($this->constraints);
	}

	/**
	 * Return the constraints as a PHP array.
	 */
	public function __toArray(): array
	{
		return $this->constraints;
	}
}
