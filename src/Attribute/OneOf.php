<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;

/**
 * A "one_of" attribute.
 *
 * The "one_of" attribute is used to specify a list of values that a
 * field can accept. If a value is not in the list, then the field is
 * considered invalid.
 */
final class OneOf extends Attribute implements Constraint, \IteratorAggregate, \Countable
{
	public function __construct(array $value)
	{
		parent::__construct('one_of', $value);
	}

	public function getIterator(): \ArrayIterator
	{
		return new \ArrayIterator($this->value);
	}

	public function count(): int
	{
		return count($this->value);
	}
}
