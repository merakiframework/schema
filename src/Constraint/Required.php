<?php
declare(strict_types=1);

namespace Meraki\Form\Constraint;

use Meraki\Form\Constraint;
use Meraki\Form\Constraint\ValidationResult;

final class Required implements Constraint
{
	public function __construct(public bool $value = true)
	{
	}

	public function validate(mixed $value): ValidationResult
	{
		if ($this->value && ($value === null || $value === '')) {
			return ValidationResult::fail('Expected a value to be provided.');
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
