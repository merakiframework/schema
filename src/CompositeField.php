<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Field;

class CompositeField extends Field implements \IteratorAggregate, \Countable
{
	public Field\Set $fields;

	public function __construct(Attribute\Type $type, Attribute\Name $name)
	{
		parent::__construct($type, $name);

		$this->fields = new Field\Set();

		// $this->fields = new Field\Set(...$fields);
		$this->name($name->value);
	}

	public function name(string $name): static
	{
		$this->fields->prefixNamesWith($name);

		return $this;
	}

	public function add(Field ...$fields): static
	{
		$this->fields->mutableAdd(...$fields);
		$this->fields->prefixNamesWith($this->name->value);

		return $this;
	}

	public function getIterator(): \Traversable
	{
		return $this->fields->getIterator();
	}

	public function count(): int
	{
		return $this->fields->count();
	}
}
