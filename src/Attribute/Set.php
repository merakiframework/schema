<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;

final class Set implements \Countable, \IteratorAggregate
{
	public const ALLOW_ANY = [];

	public const ALLOW_ALWAYS_SUPPORTED_ONLY = [
		Attribute::class,
		Attribute\DefaultValue::class,
		Attribute\Optional::class,
		Attribute\Name::class,
		Attribute\Value::class,
		Attribute\Type::class,
	];

	protected static array $alwaysSupportedAttributes = [
		Attribute::class,
		Attribute\DefaultValue::class,
		Attribute\Optional::class,
		Attribute\Name::class,
		Attribute\Value::class,
		Attribute\Type::class,
	];

	private array $attributes = [];

	private array $allowedAttributes = [];

	public function __construct(array $whitelist, Attribute ...$attributes)
	{
		$this->mutableAllow(...$whitelist);
		$this->mutableAdd(...$attributes);
	}

	/**
	 * @param class-string ...$attributes
	 */
	public function allow(string ...$attributes): self
	{
		$copy = clone $this;
		$copy->allowedAttributes = array_merge($this->allowedAttributes, $attributes);

		return $copy;
	}

	private function mutableAllow(string ...$attributes): void
	{
		$this->allowedAttributes = array_merge($this->allowedAttributes, $attributes);
	}

	public function set(Attribute ...$attributes): self
	{
		return (clone $this)->remove(...$attributes)->add(...$attributes);
	}

	public function findByName(string $name): ?Attribute
	{
		foreach ($this->attributes as $attribute) {
			if ($attribute->hasNameOf($name)) {
				return $attribute;
			}
		}

		return null;
	}

	public function getByName(string $name): Attribute
	{
		if (($attribute = $this->findByName($name)) !== null) {
			return $attribute;
		}

		throw new \InvalidArgumentException('Attribute with name "' . $name . '" does not exist.');
	}

	public function getIterator(): \ArrayIterator
	{
		return new \ArrayIterator($this->attributes);
	}

	public function count(): int
	{
		return count($this->attributes);
	}

	public function getConstraints(): self
	{
		return $this->filter(fn(Attribute $attribute): bool => $attribute instanceof Constraint);
	}

	public function filter(callable $callback): self
	{
		$filteredAttributes = array_filter($this->attributes, $callback);

		return new self($this->allowedAttributes, ...$filteredAttributes);
	}

	public function remove(Attribute ...$attributes): self
	{
		$copy = clone $this;
		$copy->mutableRemove(...$attributes);

		return $copy;
	}

	public function merge(self $set): self
	{
		$copy = clone $this;
		$copy->mutableAdd(...$set->attributes);

		return $copy;
	}

	public function contains(Attribute $attribute): bool
	{
		return $this->indexOf($attribute) !== null;
	}

	public function isEmpty(): bool
	{
		return count($this->attributes) === 0;
	}

	private function mutableRemove(Attribute ...$attribute): void
	{
		foreach ($attribute as $attr) {
			$index = $this->indexOf($attr);

			if ($index !== null) {
				unset($this->attributes[$index]);
			}
		}
	}

	private function mutableAdd(Attribute ...$attributes): void
	{
		$this->assertAttributesAreAllowed(...$attributes);

		foreach ($attributes as $attribute) {
			if (!$this->exists($attribute)) {
				$this->attributes[] = $attribute;
			}
		}
	}

	public function exists(Attribute $attribute): bool
	{
		return $this->indexOf($attribute) !== null;
	}

	public function indexOf(Attribute $attribute): ?int
	{
		foreach ($this->attributes as $index => $storedAttribute) {
			if ($storedAttribute->hasNameOf($attribute->name)) {
				return $index;
			}
		}

		return null;
	}

	public function add(Attribute ...$attributes): self
	{
		$copy = clone $this;
		$copy->mutableAdd(...$attributes);

		return $copy;
	}

	public function __toArray(): array
	{
		return $this->attributes;
	}

	private function assertAttributesAreAllowed(Attribute ...$attributes): void
	{
		foreach ($attributes as $attribute) {
			if (!$this->isAllowed($attribute)) {
				throw new \InvalidArgumentException('Attribute "' . $attribute->name . '" is not allowed.');
			}
		}
	}

	public function isAllowed(Attribute $attribute): bool
	{
		// No whitelist means any attribute is allowed
		if ($this->allowedAttributes === self::ALLOW_ANY) {
			return true;
		}

		if (in_array($attribute::class, self::ALLOW_ALWAYS_SUPPORTED_ONLY, true)) {
			return true;
		}

		return in_array($attribute::class, $this->allowedAttributes, true);
	}
}
