<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Rule\Condition;
use Meraki\Schema\SchemaFacade;

class ConditionGroup extends Condition implements \IteratorAggregate, \Countable
{
	public ?self $parent = null;

	public array $conditions = [];

	public function __construct(
		public string $type,
		Condition ...$conditions
	) {
		if (!in_array($type, ['all', 'any', 'none'])) {
			throw new \InvalidArgumentException('Invalid condition group type: ' . $type);
		}

		foreach ($conditions as $condition) {
			$this->add($condition);
		}
	}

	public function mustMatchAll(): bool
	{
		return $this->type === 'all';
	}

	public function mustMatchSome(): bool
	{
		return $this->type === 'any';
	}

	public function cannotMatchAny(): bool
	{
		return $this->type === 'none';
	}

	public function evaluate(array $data, SchemaFacade $schema): bool
	{
		return match ($this->type) {
			'all' => $this->evaluateAll($data, $schema),
			'any' => $this->evaluateAny($data, $schema),
			'none' => $this->evaluateNone($data, $schema),
			default => false,
		};
	}

	private function evaluateAll(array $data, SchemaFacade $schema): bool
	{
		foreach ($this->conditions as $condition) {
			if (!$condition->evaluate($data, $schema)) {
				return false;
			}
		}

		return true;
	}

	private function evaluateAny(array $data, SchemaFacade $schema): bool
	{
		foreach ($this->conditions as $condition) {
			if ($condition->evaluate($data, $schema)) {
				return true;
			}
		}

		return false;
	}

	private function evaluateNone(array $data, SchemaFacade $schema): bool
	{
		foreach ($this->conditions as $condition) {
			if ($condition->evaluate($data, $schema)) {
				return false;
			}
		}

		return true;
	}

	public function add(Condition $condition): self
	{
		if ($condition instanceof self) {
			$condition->parent = $this;
		}

		$this->conditions[] = $condition;

		return $this;
	}

	public function last(): ?Condition
	{
		return $this->conditions[count($this->conditions) - 1] ?? null;
	}

	public function remove(Condition $condition): self
	{
		$index = array_search($condition, $this->conditions, true);

		if ($index !== false) {
			unset($this->conditions[$index]);
		}

		return $this;
	}

	public function replace(Condition $oldCondition, Condition $newCondition): self
	{
		$index = array_search($oldCondition, $this->conditions, true);

		if ($index !== false) {
			$this->conditions[$index] = $newCondition;
		}

		return $this;
	}

	public function getIterator(): \ArrayIterator
	{
		return new \ArrayIterator($this->conditions);
	}

	/**
	 * Counts all nested conditions, but not the groups themselves.
	 */
	public function count(): int
	{
		$count = 0;

		foreach ($this->conditions as $condition) {
			if ($condition instanceof self) {
				$count += $condition->count();
			} else {
				$count++;
			}
		}

		return $count;
	}

	public static function allOf(Condition ...$conditions): self
	{
		return new self('all', ...$conditions);
	}

	public static function anyOf(Condition ...$conditions): self
	{
		return new self('any', ...$conditions);
	}

	public static function noneOf(Condition ...$conditions): self
	{
		return new self('none', ...$conditions);
	}

	public function __toObject(): object
	{
		return (object) [
			'group' => $this->type,
			'conditions' => array_map(fn(Condition $condition): object => $condition->__toObject(), $this->conditions),
		];
	}

	public function __toArray(): array
	{
		$conditions = [];

		foreach ($this->conditions as $condition) {
			$conditions[] = $condition->__toArray();
		}

		return [
			'group' => $this->type,
			'conditions' => $conditions
		];
	}
}
