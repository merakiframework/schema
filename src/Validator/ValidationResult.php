<?php
declare(strict_types=1);

namespace Meraki\Schema\Validator;

use Meraki\Schema\Validator;
use Meraki\Schema\ValidationStatus;
use Meraki\Schema\ValidationResult as ValidationResultInterface;

final class ValidationResult implements ValidationResultInterface
{
	public function __construct(public readonly ValidationStatus $status, public readonly Validator $validator)
	{
	}

	public static function fail(Validator $validator): self
	{
		return new self(ValidationStatus::Failed, $validator);
	}

	public static function pass(Validator $validator): self
	{
		return new self(ValidationStatus::Passed, $validator);
	}

	public static function skip(Validator $validator): self
	{
		return new self(ValidationStatus::Skipped, $validator);
	}

	public static function pending(Validator $validator): self
	{
		return new self(ValidationStatus::Pending, $validator);
	}
}
