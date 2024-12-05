<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Rule;

class Set implements \IteratorAggregate, \Countable
{
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

	public function mutableAdd(Rule ...$rules): void
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

	public function mutableRemove(Rule $rule): void
	{
		foreach ($this->rules as $index => $storedRule) {
			if ($storedRule === $rule) {
				unset($this->rules[$index]);
			}
		}
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

	public function getIterator(): \ArrayIterator
	{
		return new \ArrayIterator($this->rules);
	}

	public function __toArray(): array
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
