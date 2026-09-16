<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Facade;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\ConditionGroup;
use Meraki\Schema\Rule\Outcome;
use Meraki\Schema\Rule\AppliedOutcome;

class Rule
{
	public function __construct(
		public readonly ConditionGroup $condition,
		/** @var array<Outcome> what happens when the condition holds */
		public readonly array $outcomes,
		/**
		 * What happens when it does not.
		 *
		 * Writing the else-branch here rather than as a second rule with a hand-inverted
		 * condition is the point: two rules drift, and nothing checks that the inverted copy
		 * still says the opposite of the original. One condition, read once, cannot disagree
		 * with itself.
		 *
		 * @var array<Outcome>
		 */
		public readonly array $else = [],
	) {
	}

	/**
	 * Decides which branch fires, and reports the outcomes in it.
	 *
	 * Nothing is applied here. An outcome is an operation on a field, and finding the field
	 * belongs to whoever holds the schema — {@see Facade} does it, in `applyRules()` — so this
	 * stays a question about the data and changes nothing.
	 *
	 * Reporting what fired is what lets a result say *why* a field is optional. Without it the
	 * only way to find out is to evaluate every rule again, which is what `meraki/schema-html`
	 * does today in a method that duplicates this engine.
	 *
	 * @return list<AppliedOutcome>
	 */
	public function evaluate(Facade $schema, array $data): array
	{
		$matched = $this->condition->matches($data, $schema);

		return array_map(
			fn(Outcome $outcome): AppliedOutcome => new AppliedOutcome($this, $outcome, $matched),
			array_values($matched ? $this->outcomes : $this->else),
		);
	}
}
