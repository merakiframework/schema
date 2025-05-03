<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Property;

/**
 * @extends Field<mixed>
 */
abstract class Atomic extends Field
{
	protected function process($value): Property\Value
	{
		return new Property\Value($value);
	}
}
