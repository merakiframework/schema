<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;

/**
 * A "default value" attribute.
 *
 * The default value attribute is only used when a field value has not been
 * provided. A default value goes through the same validation process as a
 * field value.
 */
final class DefaultValue extends Attribute
{
	public function __construct(mixed $value)
	{
		parent::__construct('default_value', $value);
	}
}
