<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Rule\Outcome;
use Meraki\Schema\SchemaFacade;

final class OutcomeGroup implements \IteratorAggregate, \Countable
{
	/**
	 * @var Outcome[] $outcomes
	 */
	private array $outcomes = [];

	/**
	 * @param Outcome[] $outcomes
	 */
	public function __construct(Outcome ...$outcomes)
	{
		$this->mutableAdd(...$outcomes);
	}

	public function indexOf(Outcome $outcome): ?int
	{
		foreach ($this->outcomes as $index => $storedOutcome) {
			if ($storedOutcome === $outcome) {
				return $index;
			}
		}

		return null;
	}

	public function execute(array $data, SchemaFacade $schema): void
	{
		foreach ($this->outcomes as $outcome) {
			$outcome->execute($data, $schema);
		}
	}

	public function first(): ?Outcome
	{
		return $this->outcomes[0] ?? null;
	}

	public function exists(Outcome $outcome): bool
	{
		return $this->indexOf($outcome) !== null;
	}

	public function mutableAdd(Outcome ...$outcomes): void
	{
		foreach ($outcomes as $outcome) {
			if (!$this->exists($outcome)) {
				$this->outcomes[] = $outcome;
			}
		}
	}

	public function add(Outcome ...$outcomes): self
	{
		$clone = clone $this;
		$clone->mutableAdd(...$outcomes);

		return $clone;
	}

	public function mutableRemove(Outcome $outcome): void
	{
		foreach ($this->outcomes as $index => $storedOutcome) {
			if ($storedOutcome === $outcome) {
				unset($this->outcomes[$index]);
			}
		}
	}

	public function remove(Outcome $outcome): self
	{
		$clone = clone $this;
		$clone->mutableRemove($outcome);

		return $clone;
	}

	public function contains(Outcome $outcome): bool
	{
		return $this->indexOf($outcome) !== null;
	}

	public function getIterator(): \ArrayIterator
	{
		return new \ArrayIterator($this->outcomes);
	}

	public function __toArray(): array
	{
		return $this->outcomes;
	}

	public function count(): int
	{
		return count($this->outcomes);
	}

	public function isEmpty(): bool
	{
		return count($this->outcomes) === 0;
	}
}
