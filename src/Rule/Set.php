<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Rule;

/**
 * @implements \IteratorAggregate<int, Rule>
 */
class Set implements \IteratorAggregate, \Countable
{
	/** @var list<Rule> */
	private array $rules = [];

	public function __construct(Rule ...$rules)
	{
		$this->mutableAdd(...$rules);
	}

	public function indexOf(Rule $rule): ?int
	{
		foreach ($this->rules as $index => $storedRule) {
			if ($storedRule === $rule) {
				return $index;
			}
		}

		return null;
	}


	public function first(): ?Rule
	{
		return $this->rules[0] ?? null;
	}

	public function exists(Rule $rule): bool
	{
		return $this->indexOf($rule) !== null;
	}

	private function mutableAdd(Rule ...$rules): void
	{
		foreach ($rules as $rule) {
			if (!$this->exists($rule)) {
				$this->rules[] = $rule;
			}
		}
	}

	public function add(Rule ...$rules): self
	{
		$clone = clone $this;
		$clone->mutableAdd(...$rules);

		return $clone;
	}

	/**
	 * Private, for the reason {@see self::mutableAdd()} gives.
	 *
	 * The renumbering is not cosmetic. `unset()` leaves a hole, so a set that had ever removed
	 * anything stopped being a list while still claiming to be one — the same defect that made
	 * `getFailed()->getFirst()` return null on a result that plainly had failures.
	 */
	private function mutableRemove(Rule $rule): void
	{
		$this->rules = array_values(
			array_filter($this->rules, static fn(Rule $stored): bool => $stored !== $rule),
		);
	}

	public function remove(Rule $rule): self
	{
		$clone = clone $this;
		$clone->mutableRemove($rule);

		return $clone;
	}

	public function contains(Rule $rule): bool
	{
		return $this->indexOf($rule) !== null;
	}

	/**
	 * @return \ArrayIterator<int, Rule>
	 */
	public function getIterator(): \ArrayIterator
	{
		return new \ArrayIterator($this->rules);
	}

	/** @return list<Rule> */
	public function toArray(): array
	{
		return $this->rules;
	}

	public function count(): int
	{
		return count($this->rules);
	}

	public function isEmpty(): bool
	{
		return count($this->rules) === 0;
	}
}
