<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Field;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;

interface Validator
{
	public function validate(Attribute&Constraint $constraint, Field $field): bool;
}
