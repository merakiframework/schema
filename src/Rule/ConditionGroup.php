<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

/**
 * Several conditions asked as one — {@see Condition\AllOf} and {@see Condition\AnyOf}.
 *
 * There was an `add()` here, which wrote to `$conditions` and returned `$this`. It was the only
 * mutating method anywhere in the rule model, where `Rule\Set::add()` and `Draft::then()` both
 * clone, and it had no caller in this package or either sibling. Nothing had gone wrong yet; the
 * problem was that a rule is meant to be a value, and the concurrency guarantee in the README —
 * one schema serving many requests — rests on a composed condition not being writable by whoever
 * happens to be holding it. Both implementations are `readonly` now, so that is structural
 * rather than a matter of nobody having called the method.
 */
interface ConditionGroup extends Condition
{
	/**
	 * The conditions this holds, in order.
	 *
	 * Both implementations already had it; declaring it is what lets a caller inspect a composed
	 * condition without knowing whether it is an `allOf` or an `anyOf`. {@see Guards} needs
	 * exactly that, to check every comparison in a rule at the point the rule is written rather
	 * than when it first fires.
	 *
	 * @return list<Condition>
	 */
	public function conditions(): array;
}
