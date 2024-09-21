<?php
declare(strict_types=1);

namespace Meraki\Form\Constraint;

class ValidationResult
{
	public const PASSED = 0;
	public const FAILED = 1;

	public function __construct(
		public readonly int $status,
		public readonly string $reason = ''
	) {
	}

	public static function pass(): self
	{
		return new self(self::PASSED);
	}

	public static function fail(string $reason): self
	{
		return new self(self::FAILED, $reason);
	}

	public function passed(): bool
	{
		return $this->status === self::PASSED;
	}

	public function failed(): bool
	{
		return $this->status === self::FAILED;
	}
}
