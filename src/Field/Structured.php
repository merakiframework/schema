<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Property;

/**
 * @extends Field<null|string>
 */
abstract class Structured extends Field
{
	protected function process($value): Property\Value
	{
		return new Property\Value($value);
	}
}
