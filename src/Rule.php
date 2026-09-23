<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Facade;
use Meraki\Schema\Rule\ConditionGroup;
use Meraki\Schema\Rule\Outcome;
use Meraki\Schema\Rule\AppliedOutcome;

class Rule
{
	public function __construct(
		public readonly ConditionGroup $condition,

		/** @var array<Outcome> what happens when the condition holds */
		public readonly array $outcomes,

		/**  @var array<Outcome> What happens when it does not */
		public readonly array $else = [],
	) {
	}

	/**
	 * Decide which branch fires, and report the outcomes (then or else) for it.
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
