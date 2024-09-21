<?php
declare(strict_types=1);

namespace Meraki\Form\Constraint;

use Meraki\Form\Constraint;
use Meraki\Form\Constraint\ValidationResult;

final class Min implements Constraint
{
	public function __construct(public int $value)
	{
	}

	public function validate(mixed $value): ValidationResult
	{
		if (is_string($value) && mb_strlen($value) < $this->value) {
			return ValidationResult::fail('Expected value to be at least '.$this->value.' characters long.');
		}

		if ((is_int($value) || is_float($value)) && $value < $this->value) {
			return ValidationResult::fail('Expected a value greater than or equal to '.$this->value.'.');
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
