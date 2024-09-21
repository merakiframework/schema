<?php
declare(strict_types=1);

namespace Meraki\Form\Constraint;

use Meraki\Form\Constraint;
use Meraki\Form\Constraint\ValidationResult;

final class Pattern implements Constraint
{
	public function __construct(public string $value)
	{
	}

	public function validate(mixed $value): ValidationResult
	{
		if (!preg_match($this->value, $value)) {
			return ValidationResult::fail('Expected value to match pattern: '.$this->value);
		}

		return ValidationResult::pass();
	}

	public function hasValueOf(mixed $value): bool
	{
		return $this->value === $value;
	}

	public function equals(Constraint $other): bool
	{
		return $other instanceof self && $other->hasValueOf($this->value);
	}
}
