<?php
declare(strict_types=1);

namespace Meraki\Schema;

/**
 * Represents every key=value pair in a schema object.
 *
 * @property-read string $name
 * @property-read mixed $value
 */
class Property
{
	public function __construct(
		public readonly string $name,
		public readonly mixed $value,
	) {}

	public function hasNameOf(string $name): bool
	{
		return $this->name === $name;
	}

	public function hasValueOf(mixed $value): bool
	{
		return $this->value === $value;
	}

	public function equals(self $other): bool
	{
		return $this->name === $other->name && $this->value === $other->value;
	}
}
