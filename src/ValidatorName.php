<?php
declare(strict_types=1);

namespace Meraki\Schema;

final class ValidatorName
{
	public function __construct(
		public readonly string $value
	) {
		if (preg_match('/^[a-z][a-z0-9_]*$/', $this->value) !== 1) {
			throw new \InvalidArgumentException('Validator name must begin with a lowercase letter and contain only lowercase letters, numbers, and underscores.');
		}
	}

	public static function normalize(string $name): self
	{
		return new self(strtolower($name));
	}

	public function __toString(): string
	{
		return $this->value;
	}

	public function equals(self $other): bool
	{
		return (string)$this === (string)$other;
	}
}
