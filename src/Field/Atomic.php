<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Field\ValidationResult;

/**
 * @phpstan-import-type SerializedField from Field
 * @template AcceptedType of mixed
 * @template TSerialized of SerializedField
 * @extends Field<AcceptedType, TSerialized>
 */
abstract class Atomic extends Field
{
	public function validate(): ValidationResult
	{
		return parent::validate();
	}
}
