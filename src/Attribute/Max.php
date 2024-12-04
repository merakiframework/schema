<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Constraint;
use Meraki\Schema\Attribute;

/**
 * A "max" attribute.
 *
 * The max attribute is used to specify the maximum value/length, or some other variable, for a field.
 */
final class Max extends Attribute implements Constraint
{
	public function __construct(mixed $value)
	{
		parent::__construct('max', $value);
	}
}
