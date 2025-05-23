<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\ValidationResult;
use Meraki\Schema\ValidationStatus;

final class ConstraintValidationResult implements ValidationResult
{
	public function __construct(
		public readonly ValidationStatus $status,
		public readonly string $name,
	) {
	}

	public static function pass(string $name): self
	{
		return new self(ValidationStatus::Passed, $name);
	}

	public static function fail(string $name): self
	{
		return new self(ValidationStatus::Failed, $name);
	}

	public static function skip(string $name): self
	{
		return new self(ValidationStatus::Skipped, $name);
	}
}
