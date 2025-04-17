<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Field;
use Meraki\Schema\ValidatorName;
use InvalidArgumentException;

/**
 * @property-read ValidatorName $name
 */
interface Validator
{
	/**
	 * @throws InvalidArgumentException when the value is the incorrect type for the validator
	 */
	public function validate(FieldValue $value, Field $field): bool;
}
