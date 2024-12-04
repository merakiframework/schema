<?php
declare(strict_types=1);

namespace Meraki\Schema\Validator;

use Meraki\Schema\Field;
use Meraki\Schema\Constraint;
use Meraki\Schema\Attribute;
use Meraki\Schema\Validator;

final class CheckMaxCharCount implements Validator
{
	public function validate(Attribute&Constraint $constraint, Field $field): bool
	{
		return is_string($field->value) && (mb_strlen($field->value) <= $constraint->value);
	}
}
