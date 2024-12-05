<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Property;

class Attribute implements Property
{
	public function __construct(
		public readonly string $name,
		public mixed $value,
	) {

	}

	public function makeConstrainable(): Attribute&Constraint
	{
		return new class($this->name, $this->value) extends Attribute implements Constraint
		{
			public function __construct(string $name, mixed $value)
			{
				parent::__construct($name, $value);
			}
		};
	}

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

	public function isScoped(): bool
	{
		try {
			$this->getScope();
			return true;
		} catch (\InvalidArgumentException $e) {
			return false;
		}
	}

	public function getScope(): Scope
	{
		return new Scope($this->value);
	}

	public function __toString(): string
	{
		return $this->value;
	}
}
