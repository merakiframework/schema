<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\FieldName;
use IteratorAggregate;
use Countable;
use InvalidArgumentException;

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

	/**
	 * The field of that name, or a failure — never `null`.
	 *
	 * Typed `Field` rather than `?Field`, which is what it always returned: it threw on a miss and
	 * the nullable type was a lie every caller had to write a dead check against. {@see self::findByName()}
	 * is the nullable one, and the pair reads as the difference — *get* it, or go *find* whether it
	 * is there.
	 *
	 * @throws InvalidArgumentException if no field of that name is present
	 */
	public function getByName(string|FieldName $name): Field
	{
		return $this->findByName($name)
			?? throw new InvalidArgumentException(sprintf('Field with name "%s" does not exist.', (string) $name));
	}

	public function findByName(string|FieldName $name): ?Field
	{
		if (is_string($name)) {
			$name = new FieldName($name);
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

	/**
	 * Private, because a set handed to a schema must not be changeable from outside it.
	 *
	 * {@see \Meraki\Schema\Facade::copyForRequest()} shares the very same instance rather than
	 * copying it, on the grounds that every way of changing one returns a new set. That was true
	 * of {@see self::add()} and {@see self::replace()} and was not true of this, so one caller
	 * reaching in here changed a definition every concurrent request was reading.
	 *
	 * @throws InvalidArgumentException if a field with the same name is already present
	 */
	private function mutableAdd(Field ...$fields): void
	{
		foreach ($fields as $field) {
			// Names identify fields, so a second one under an existing name is a mistake
			// in the schema definition. Silently discarding it loses the definition and
			// gives no clue where it went.
			if ($this->exists($field)) {
				throw new InvalidArgumentException(sprintf(
					'A field named "%s" already exists.',
					(string) $field->name,
				));
			}

			$this->fields[] = $field;
		}
	}


	public function add(Field ...$fields): self
	{
		$clone = clone $this;
		$clone->mutableAdd(...$fields);

		return $clone;
	}

	/**
	 * Puts a field in the place of the one sharing its name, keeping the set's order.
	 *
	 * This is what a rule outcome needs. A field is immutable, so `makeOptional()` hands back
	 * a *copy* and the set still holds the original — which is exactly the bug this closes:
	 * every outcome called a wither and discarded it, so rules silently did nothing.
	 *
	 * Order is preserved rather than removing and appending, because rules are applied in
	 * order and a later one may read a field an earlier one changed. Re-ordering the set as a
	 * side effect of changing one field would make that depend on which rules happened to
	 * fire.
	 *
	 * @throws InvalidArgumentException if no field of that name is present
	 */
	public function replace(Field $field): self
	{
		$index = $this->indexOf($field);

		if ($index === null) {
			throw new InvalidArgumentException(sprintf(
				'No field named "%s" to replace.',
				(string) $field->name,
			));
		}

		$clone = clone $this;
		$clone->fields[$index] = $field;

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
	public function toArray(): array
	{
		return $this->fields;
	}

	public function isEmpty(): bool
	{
		return count($this->fields) === 0;
	}
}
