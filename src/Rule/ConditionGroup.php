<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Rule\Condition;

interface ConditionGroup extends Condition
{
	public function add(Condition $condition): static;

	/**
	 * The conditions this holds, in order.
	 *
	 * Both implementations already had it; declaring it is what lets a caller inspect a composed
	 * condition without knowing whether it is an `allOf` or an `anyOf`. {@see \Meraki\Schema\Facade::addRule()}
	 * needs exactly that, to check every comparison in a rule at the point the rule is written
	 * rather than when it first fires.
	 *
	 * @return array<Condition>
	 */
	public function conditions(): array;
}
