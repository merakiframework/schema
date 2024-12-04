<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Validator;
use Meraki\Schema\Attribute;

/**
 * An `Attribute` that is paired with a validator, is known as
 * a `Constraint`. Attributes that can be validated must implement
 * this interface.
 */
interface Constraint
{
	// public Validator $validator { get; }
	// public function assign(Validator $validator): Attribute;
}
