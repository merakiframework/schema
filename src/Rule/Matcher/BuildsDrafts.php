<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Matcher;

use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\Draft;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Rule\Quantifier;
use Meraki\Schema\Rule\Scoped;
use Meraki\Schema\Scope;

/**
 * The one step every verb finishes with, so there is one place to change what a verb produces.
 *
 * The twelve verbs in {@see AsksAnything}, {@see AsksOrder} and {@see AsksText} all end by wrapping
 * a condition in a {@see Draft}. Routing that through here is what lets a matcher bound to a
 * *column* quantify all twelve without any of them knowing: `whereAny('sku')->equals('HAZMAT')`
 * builds the same `Equals` as `$sku->when()->equals('HAZMAT')` and wraps it.
 *
 * Held in its own trait rather than in each of the three verb traits, because a matcher uses
 * several of them and two traits declaring one method is a conflict.
 */
trait BuildsDrafts
{
	/**
	 * The same matcher, rebound to a column and quantified — {@see Matcher::quantifiedAt()}.
	 *
	 * A copy rather than a fresh construction, which is the whole point: it needs no knowledge
	 * of the using class's constructor. The previous form, `new ($matcher::class)($scope, $how)`,
	 * assumed a two-argument signature the interface never required, and silently produced a
	 * dead rule for any matcher that did not happen to have one.
	 */
	public function quantifiedAt(Scope $scope, Quantifier $how): static
	{
		return clone($this, ['scope' => $scope, 'quantifier' => $how]);
	}

	/**
	 * A condition, ready to have outcomes attached — quantified first when this matcher asks about
	 * every row rather than about one value.
	 */
	protected function draft(Condition $condition): Draft
	{
		if ($this->quantifier === null || !$condition instanceof Scoped) {
			return new Draft($condition);
		}

		return new Draft(new Condition\Quantified($this->quantifier, $condition));
	}
}
