<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Field\Serialized;
use Meraki\Schema\Field\ValidationResult;

/**
 * @template AcceptedType of mixed
 * @template TSerialized of Serialized
 * @extends Field<AcceptedType, TSerialized>
 */
abstract class Atomic extends Field
{
	public function validate(): ValidationResult
	{
		return parent::validate();
	}
}
