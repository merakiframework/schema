<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;

/**
 * A "type" attribute.
 *
 * The "type" attribute is used to specify the type of a field,
 * which governs how the value is validated.
 */
final class Type extends Attribute
{
	public function __construct(string $value)
	{
		parent::__construct('type', $value);
	}
}
