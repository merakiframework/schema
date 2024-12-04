<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;

/**
 * A "optional" attribute.
 *
 * The optional attribute is used to specify whether a field is optional
 * or required. If the "optional" attribute is missing from the field,
 * then the field is considered required.
 */
final class Optional extends Attribute
{
	public function __construct(bool $value = true)
	{
		parent::__construct('optional', $value);
	}
}
