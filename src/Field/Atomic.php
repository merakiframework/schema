<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Field\ValidationResult;

/**
 * @template AcceptedType of mixed
 * @extends Field<AcceptedType>
 */
abstract class Atomic extends Field
{
	public function validate(): ValidationResult
	{
		return parent::validate();
	}
}
