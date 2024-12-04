<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Constraint;
use Meraki\Schema\Attribute;

/**
 * A "step" attribute.
 *
 * The step attribute is used to specify the increment/step value for a field.
 */
final class Step extends Attribute implements Constraint
{
	public function __construct(mixed $value)
	{
		parent::__construct('step', $value);
	}
}
