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
		/** @var array<Outcome> */
		public readonly array $outcomes,
	) {
	}

	/**
	 * Applies this rule's outcomes if its condition holds, and reports what it did.
	 *
	 * Returning the applied outcomes is what lets a result say *why* a field is optional.
	 * Without it the only way to find out is to evaluate every rule again, which is what
	 * `meraki/schema-html` does today in a method that duplicates this engine.
	 *
	 * @return list<AppliedOutcome>
	 */
	public function evaluate(Facade $schema, array $data): array
	{
		if (!$this->condition->matches($data, $schema)) {
			return [];
		}

		$applied = [];

		foreach ($this->outcomes as $outcome) {
			$outcome->apply($schema);
			$applied[] = new AppliedOutcome($this, $outcome);
		}

		return $applied;
	}
}
