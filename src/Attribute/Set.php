<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;

/**
 * @template T of Attribute
 * @implements \IteratorAggregate<int, T>
 */
final class Set implements \Countable, \IteratorAggregate
{
	/** @var list<class-string<Attribute>> */
	public const ALLOW_ANY = [];

	/** @var list<class-string<Attribute>> */
	public const ALLOW_ALWAYS_SUPPORTED_ONLY = [
		Attribute::class,
		Attribute\DefaultValue::class,
		Attribute\Optional::class,
		Attribute\Name::class,
		Attribute\Value::class,
		Attribute\Type::class,
	];

	/** @var list<class-string<Attribute>> */
	protected static array $alwaysSupportedAttributes = [
		Attribute::class,
		Attribute\DefaultValue::class,
		Attribute\Optional::class,
		Attribute\Name::class,
		Attribute\Value::class,
		Attribute\Type::class,
	];

	/** @var list<T> */
	private array $attributes = [];

	/** @var list<class-string<T>> */
	private array $allowedAttributes = [];

	/**
	 * @param list<class-string<T>> $whitelist
	 * @param T ...$attributes
	 */
	public function __construct(array $whitelist, Attribute ...$attributes)
	{
		$this->mutableAllow(...$whitelist);
		$this->mutableAdd(...$attributes);
	}

	/**
	 * @param class-string<Attribute> ...$attributes
	 */
	public function allow(string ...$attributes): self
	{
		$copy = clone $this;
		$copy->allowedAttributes = array_merge($this->allowedAttributes, $attributes);

		return $copy;
	}

	/**
	 * @param class-string<Attribute> ...$attributes
	 */
	private function mutableAllow(string ...$attributes): void
	{
		$this->allowedAttributes = array_merge($this->allowedAttributes, $attributes);
	}

	/**
	 * @param T ...$attributes
	 * @return Set<T>
	 */
	public function set(Attribute ...$attributes): self
	{
		return (clone $this)->remove(...$attributes)->add(...$attributes);
	}

	/**
	 * @template U of T
	 * @param class-string<U> $name Fully qualified class name of the attribute
	 * @return U|null The attribute instance if found, or null
	 */
	public function findByName(string $name): ?Attribute
	{
		foreach ($this->attributes as $attribute) {
			if ($attribute->hasNameOf($name)) {
				return $attribute;
			}
		}

		return null;
	}

	/**
	 * @template U of T
	 * @param class-string<U> $name Fully qualified class name of the attribute
	 * @return U The attribute instance if found
	 * @throws \InvalidArgumentException if not found
	 */
	public function getByName(string $name): Attribute
	{
		if (($attribute = $this->findByName($name)) !== null) {
			return $attribute;
		}

		throw new \InvalidArgumentException('Attribute with name "' . $name . '" does not exist.');
	}

	/**
	 * @return \ArrayIterator<int, T>
	 */
	public function getIterator(): \ArrayIterator
	{
		return new \ArrayIterator($this->attributes);
	}

	public function count(): int
	{
		return count($this->attributes);
	}

	/**
	 * @return Set<Constraint>
	 */
	public function getConstraints(): self
	{
		return $this->filter(fn(Attribute $attribute): bool => $attribute instanceof Constraint);
	}

	/**
	 * @param callable(T): bool $callback
	 * @return Set<T>
	 */
	public function filter(callable $callback): self
	{
		$filteredAttributes = array_filter($this->attributes, $callback);

		return new self($this->allowedAttributes, ...$filteredAttributes);
	}

	/**
	 * @param T ...$attributes
	 * @return Set<T>
	 */
	public function remove(Attribute ...$attributes): self
	{
		$copy = clone $this;
		$copy->mutableRemove(...$attributes);

		return $copy;
	}

	/**
	 * @param Set<T> $set
	 * @return Set<T>
	 */
	public function merge(self $set): self
	{
		$copy = clone $this;
		$copy->mutableAdd(...$set->attributes);

		return $copy;
	}

	/**
	 * @param T $attribute
	 */
	public function contains(Attribute $attribute): bool
	{
		return $this->indexOf($attribute) !== null;
	}

	public function isEmpty(): bool
	{
		return count($this->attributes) === 0;
	}

	/**
	 * @param T ...$attribute
	 */
	private function mutableRemove(Attribute ...$attribute): void
	{
		foreach ($attribute as $attr) {
			$index = $this->indexOf($attr);

			if ($index !== null) {
				unset($this->attributes[$index]);
			}
		}
	}

	/**
	 * @param T ...$attribute
	 */
	private function mutableAdd(Attribute ...$attributes): void
	{
		$this->assertAttributesAreAllowed(...$attributes);

		foreach ($attributes as $attribute) {
			if (!$this->exists($attribute)) {
				$this->attributes[] = $attribute;
			}
		}
	}

	/**
	 * @param T $attribute
	 */
	public function exists(Attribute $attribute): bool
	{
		return $this->indexOf($attribute) !== null;
	}

	/**
	 * @param T $attribute
	 */
	public function indexOf(Attribute $attribute): ?int
	{
		foreach ($this->attributes as $index => $storedAttribute) {
			if ($storedAttribute->hasNameOf($attribute->name)) {
				return $index;
			}
		}

		return null;
	}

	/**
	 * @param T ...$attributes
	 * @return Set<T>
	 */
	public function add(Attribute ...$attributes): self
	{
		$copy = clone $this;
		$copy->mutableAdd(...$attributes);

		return $copy;
	}

	/**
	 * @return list<T>
	 */
	public function __toArray(): array
	{
		return $this->attributes;
	}

	/**
	 * @param T ...$attributes
	 */
	private function assertAttributesAreAllowed(Attribute ...$attributes): void
	{
		foreach ($attributes as $attribute) {
			if (!$this->isAllowed($attribute)) {
				throw new \InvalidArgumentException('Attribute "' . $attribute->name . '" is not allowed.');
			}
		}
	}

	/**
	 * @param T $attribute
	 */
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
