<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Property;
use IteratorAggregate;
use Countable;

/**
 * @implements IteratorAggregate<Field>
 */
class Set implements IteratorAggregate, Countable
{
	/** @var list<Field> $fields */
	private array $fields = [];

	public function __construct(Field ...$fields)
	{
		$this->mutableAdd(...$fields);
	}

	public function prefixNamesWith(Property\Name $prefix): self
	{
		foreach ($this->fields as $field) {
			$field->rename($field->name->prefixWith($prefix));
		}

		return $this;
	}

	/**
	 * Gets the names of all fields in the set.
	 * @return string[]
	 */
	public function listFieldNames(): array
	{
		return array_map(fn(Field $field): string => (string)$field->name, $this->fields);
	}

	public function indexOf(Field $field): ?int
	{
		foreach ($this->fields as $index => $storedField) {
			if ($storedField->name->equals($field->name)) {
				return $index;
			}
		}

		return null;
	}

	public function findByName(string|Property\Name $name): ?Field
	{
		if (is_string($name)) {
			$name = new Property\Name($name);
		}

		foreach ($this->fields as $field) {
			if ($field->name->equals($name)) {
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

	public function containsDuplicateFieldTypes(): bool
	{
		$types = [];

		foreach ($this->fields as $field) {
			if (in_array((string)$field->type, $types, true)) {
				return true;
			}

			$types[] = (string)$field->type;
		}

		return false;
	}

	public function add(Field ...$fields): self
	{
		$clone = clone $this;
		$clone->mutableAdd(...$fields);

		return $clone;
	}

	/**
	 * @return \ArrayIterator<Field>
	 */
	public function getIterator(): \ArrayIterator
	{
		return new \ArrayIterator($this->fields);
	}

	public function count(): int
	{
		return count($this->fields);
	}

	/**
	 * @return list<Field>
	 */
	public function __toArray(): array
	{
		return $this->fields;
	}

	public function isEmpty(): bool
	{
		return count($this->fields) === 0;
	}
}
