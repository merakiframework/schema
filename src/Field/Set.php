<?php
declare(strict_types=1);

namespace Meraki\Form\Field;

use Meraki\Form\Field;

final class Set implements \IteratorAggregate, \Countable
{
	private array $fields = [];

	public function add(Field $field): self
	{
		$this->fields[] = $field;

		return $this;
	}

	public function validate(array $data): ValidationResult
	{
		$validationResult = new ValidationResult();

		foreach ($this->fields as $field) {
			$validationResult->addResult($field->validate($data[$field->name] ?? null));
		}

		return $validationResult;
	}

	public function getIterator(): \ArrayIterator
	{
		return new \ArrayIterator($this->fields);
	}

	public function count(): int
	{
		return count($this->fields);
	}

	public function first(): ?Field
	{
		return $this->fields[0] ?? null;
	}

	public function __toArray(): array
	{
		return array_map(fn(Field $field): array => $field->__toArray(), $this->fields);
	}
}
