<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Matcher;

use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\Draft;

/**
 * The five questions every value can answer, whatever kind of thing it is.
 *
 * Identity and presence. Two addresses can be the same address and either can be absent, even
 * though neither is *before* the other and neither has text — so these belong to every matcher
 * and the other two traits are what a value has to earn.
 */
trait AsksAnything
{
	/**
	 * Holds when the value is exactly the one given.
	 */
	public function equals(mixed $expected): Draft
	{
		return new Draft(new Condition\Equals($this->scope, $expected));
	}

	/**
	 * Holds when the value is anything but the one given.
	 */
	public function notEquals(mixed $expected): Draft
	{
		return new Draft(new Condition\NotEquals($this->scope, $expected));
	}

	/**
	 * Holds when the value is any one of those given.
	 *
	 *     $country->when()->isIn(['AU', 'NZ'])->then($gstNumber->makeRequired());
	 *
	 * @param list<mixed> $candidates
	 */
	public function isIn(array $candidates): Draft
	{
		return new Draft(new Condition\IsIn($this->scope, $candidates));
	}

	/**
	 * Holds when nothing was submitted for the field, or nothing readable was.
	 *
	 * Not the same as `equals(null)`: a null expectation means the field's *authored default*,
	 * deliberately, so on a field with one they ask different questions. See
	 * {@see Condition\Emptiness}.
	 */
	public function isEmpty(): Draft
	{
		return new Draft(new Condition\IsEmpty($this->scope));
	}

	/**
	 * Holds when something was submitted for the field.
	 */
	public function isNotEmpty(): Draft
	{
		return new Draft(new Condition\IsNotEmpty($this->scope));
	}
}
