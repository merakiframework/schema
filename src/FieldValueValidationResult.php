<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Attribute;
use Meraki\Schema\ValidationResult;

class FieldValueValidationResult implements ValidationResult
{
	public readonly Attribute\Value $value;

	public function __construct(
		public readonly int $status,
		Attribute\Value $value,
	) {
		$this->value = clone $value;
	}

	public function pending(): bool
	{
		return $this->status === self::PENDING;
	}

	public function skipped(): bool
	{
		return $this->status === self::SKIPPED;
	}

	public static function skip(Attribute\Value $value): self
	{
		return new self(ValidationResult::SKIPPED, $value);
	}

	public static function pass(Attribute\Value $value): self
	{
		return new self(ValidationResult::PASSED, $value);
	}

	public static function fail(Attribute\Value $value): self
	{
		return new self(ValidationResult::FAILED, $value);
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
