<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Exception\InvalidRule;
use Meraki\Schema\Exception\InvalidScope;
use Meraki\Schema\Exception\UnknownField;
use Meraki\Schema\Field;
use Meraki\Schema\Rule;
use Meraki\Schema\Scope;
use Meraki\Schema\ScopeResolver;

/**
 * What has to be true of a rule before it is worth keeping, checked where the rule is written.
 *
 * Lifted out of {@see \Meraki\Schema\Definition} for the same reason {@see Application} was, and it
 * is the other half of that same pairing: a schema adds rules against its own fields, and a
 * collection adds *row* rules against a copy of its template. Both are "here is a rule, here are
 * the fields it may talk about", so both want the same answer.
 *
 * They did not get it. `addRule()` ran both of these; `forEachRow()` ran neither, and had a
 * third check of its own. So a row rule could be written two ways that a schema rule could not:
 *
 * | Written as | schema rule | row rule, before this |
 * | --- | --- | --- |
 * | `$age->when()->equals('eighteen')` on a number | refused | accepted, and never fired |
 * | a scope naming a property nothing has | refused | accepted, then `InvalidScope` on a request |
 *
 * The first is the dead-rule failure that {@see self::assertExpectationsAreReadable()} exists to
 * stop, arriving through a second doorway. The second is the 500-on-a-user-request that
 * {@see self::assertScopesAreAddressable()} exists to stop, likewise.
 *
 * Both are `static` and take the fields, because the set a rule is checked against is not always
 * a schema's: for a row rule it is the collection's template.
 */
final class Guards
{
	/**
	 * Every check a rule must pass to be added, in the order whose message reads best first.
	 *
	 * @param Field\Set $fields what the rule is allowed to talk about — a schema's fields, or a
	 *        collection's template
	 * @throws InvalidRule if the rule addresses something absent, or compares against a value
	 *         the field it names could never hold
	 */
	public static function check(Rule $rule, Field\Set $fields): void
	{
		self::assertScopesAreAddressable($rule, $fields);
		self::assertExpectationsAreReadable($rule, $fields);
	}

	/**
	 * Checks that every scope a rule mentions addresses something that is really there.
	 *
	 * A scope typo used to surface as a 500 on whichever user request first matched the rule;
	 * here it fails where the rule is written. The cost is an ordering constraint that did not
	 * exist before — a rule can only be added once the fields it names are — which is the trade
	 * the check is worth making.
	 *
	 * @throws InvalidRule naming the rule's bad scope
	 */
	private static function assertScopesAreAddressable(Rule $rule, Field\Set $fields): void
	{
		$resolver = new ScopeResolver($fields);

		foreach (self::scopesIn($rule) as $scope) {
			try {
				$resolver->resolve($scope);
			} catch (InvalidScope | UnknownField $e) {
				throw InvalidRule::addressesSomethingTheSchemaCannot((string) $scope, $e);
			}
		}
	}

	/**
	 * Checks that every value a rule compares against is one the field it names could hold.
	 *
	 * A comparison the field cannot read is unequal to every input there will ever be, so the
	 * rule is dead — and a dead rule raises nothing, which makes it indistinguishable from one
	 * whose condition simply never held. `when('age')->equals('eighteen')` on a number field is
	 * the shape of it.
	 *
	 * @throws InvalidRule naming the field and the value it cannot hold
	 */
	private static function assertExpectationsAreReadable(Rule $rule, Field\Set $fields): void
	{
		foreach (self::comparisonsIn($rule->condition) as $comparison) {
			// The condition writes its own sentence, because the reasons differ and this cannot
			// tell which applied. An unreadable expectation and a field with no order are
			// different mistakes needing different corrections, and one message describing both
			// would be wrong about at least one of them.
			$why = $comparison->whyItCouldNeverHold($fields);

			if ($why !== null) {
				throw InvalidRule::because($why);
			}
		}
	}

	/**
	 * Every scope a rule mentions: what it asks about, and what both branches change.
	 *
	 * @return list<Scope>
	 */
	private static function scopesIn(Rule $rule): array
	{
		return [
			...$rule->condition->getScopes(),
			...array_map(static fn(Outcome $o): Scope => $o->getScope(), $rule->outcomes),
			...array_map(static fn(Outcome $o): Scope => $o->getScope(), $rule->else),
		];
	}

	/**
	 * Every comparison inside a condition, however deeply it was composed.
	 *
	 * @return list<Condition\Comparison>
	 */
	private static function comparisonsIn(Condition $condition): array
	{
		if ($condition instanceof Condition\Comparison) {
			return [$condition];
		}

		if (!$condition instanceof ConditionGroup) {
			return [];
		}

		$found = [];

		foreach ($condition->conditions() as $inner) {
			$found = [...$found, ...self::comparisonsIn($inner)];
		}

		return $found;
	}
}
