<?php
declare(strict_types=1);

namespace Meraki\Form;

use Meraki\Form\Constraint\ValidationResult;

/**
 * @property-read mixed $value
 */
interface Constraint
{
	public function validate(mixed $value): ValidationResult;

	public function hasValueOf(mixed $value): bool;

	public function equals(self $other): bool;
}
