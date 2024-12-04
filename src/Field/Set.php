<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;

class Set implements \IteratorAggregate, \Countable
{
	private array $fields = [];

	public function __construct(Field ...$fields)
	{
		$this->mutableAdd(...$fields);
	}

	public function indexOf(Field $field): ?int
	{
		foreach ($this->fields as $index => $currentField) {
			if ($currentField->hasNameOf($field->name)) {
				return $index;
			}
		}

		return null;
	}

	public function findByName(string $name): ?Field
	{
		foreach ($this->fields as $field) {
			if ($field->hasNameOf($name)) {
				return $field;
			}
		}

		return null;
	}

	public function first(): ?Field
	{
		return $this->fields[0] ?? null;
	}

	public function exists(Field $field): bool
	{
		return $this->indexOf($field) !== null;
	}

	public function mutableAdd(Field ...$fields): void
	{
		foreach ($fields as $field) {
			if (!$this->exists($field)) {
				$this->fields[] = $field;
			}
		}
	}

	public function add(Field ...$fields): self
	{
		$clone = clone $this;
		$clone->mutableAdd(...$fields);

		return $clone;
	}

	public function getIterator(): \ArrayIterator
	{
		return new \ArrayIterator($this->fields);
	}

	public function count(): int
	{
		return count($this->fields);
	}

	public function __toArray(): array
	{
		return $this->fields;
	}

	public function isEmpty(): bool
	{
		return count($this->fields) === 0;
	}

	public function __isset(string $name): bool
	{
		return $this->findByName($name) !== null;
	}

	public function __get(string $name): ?Field
	{
		return $this->findByName($name);
	}
}
