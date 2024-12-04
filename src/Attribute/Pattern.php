<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;

/**
 * A "pattern" attribute.
 *
 * The pattern attribute is used to specify a "format/regex" that a field's value must match.
 */
final class Pattern extends Attribute implements Constraint
{
	public function __construct(string $value)
	{
		parent::__construct('pattern', $value);
	}
}
