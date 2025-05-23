<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Property;
use IteratorAggregate;
use Countable;
use InvalidArgumentException;

abstract class Composite extends Field implements IteratorAggregate, Countable
{
	public Field\Set $fields;

	public function __construct(Property\Type $type, Property\Name $name, AtomicField ...$fields)
	{
		parent::__construct($type, $name);

		$this->fields = new Field\Set(...$fields);

		$this->rename($name);
	}

	public function rename(Property\Name $name): static
	{
		$this->name = $name;
		$this->fields->prefixNamesWith($name);

		return $this;
	}

	/** @param array $value */
	public function prefill($value): static
	{
		if (!is_array($value)) {
			throw new InvalidArgumentException('Input value must be an array.');
		}

		parent::prefill($value);

		foreach ($this->fields as $field) {
			// Skip pre-filling if the field is not present in the value array
			// This allows for partial pre-filling of fields
			if (!isset($value[(string)$field->name])) {
				continue;
			}

			$field->prefill($value[(string)$field->name]);
		}

		return $this;
	}

	/** @param array $value */
	public function input($value): static
	{
		if (!is_array($value)) {
			throw new InvalidArgumentException('Input value must be an array.');
		}

		parent::input($value);

		foreach ($this->fields as $field) {
			if (!isset($value[(string)$field->name])) {
				continue;
			}

			$field->input($value[(string)$field->name]);
		}

		return $this;
	}

	public function validate(): CompositeValidationResult
	{
		$fieldResults = [];

		foreach ($this->fields as $field) {
			$fieldResults[] = $field->validate();
		}

		return new CompositeValidationResult($this, ...$fieldResults);
	}

	public function getIterator(): \Traversable
	{
		return $this->fields->getIterator();
	}

	public function count(): int
	{
		return $this->fields->count();
	}

	public function __isset(string $name): bool
	{
		return $this->fields->findByName($this->name->__toString() . $this->name::PREFIX_SEPARATOR . $name) !== null;
	}

	public function __get($name): Field
	{
		$field = $this->fields->findByName($this->name->__toString() . $this->name::PREFIX_SEPARATOR . $name);

		if ($field) {
			return $field;
		}

		throw new InvalidArgumentException("Field '$name' does not exist.");
	}

	final protected function validateType(mixed $value): bool
	{
		return true;
	}
}
