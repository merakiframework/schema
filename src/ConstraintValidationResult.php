<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Constraint;

class ConstraintValidationResult implements ValidationResult
{
	public readonly Constraint $constraint;

	public function __construct(
		public readonly int $status,
		Constraint $constraint,
	) {
		$this->constraint = clone $constraint;
	}

	public static function pass(Constraint $constraint): self
	{
		return new self(self::PASSED, $constraint);
	}

	public static function fail(Constraint $constraint): self
	{
		return new self(self::FAILED, $constraint);
	}

	public static function guess(bool $result, Constraint $constraint): self
	{
		return new self($result ? self::PASSED : self::FAILED, $constraint);
	}

	public static function skip(Constraint $constraint): self
	{
		return new self(self::SKIPPED, $constraint);
	}

	public function skipped(): bool
	{
		return $this->status === self::SKIPPED;
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
