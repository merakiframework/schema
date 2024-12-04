<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Constraint;
use Meraki\Schema\Attribute;

/**
 * A "min" attribute.
 *
 * The min attribute is used to specify the minimum value/length, or some other variable, for a field.
 */
final class Min extends Attribute implements Constraint
{
	public function __construct(mixed $value)
	{
		parent::__construct('min', $value);
	}
}
