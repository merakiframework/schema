<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Matcher;

use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\Draft;
use Meraki\Schema\Rule\Scoped;

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
