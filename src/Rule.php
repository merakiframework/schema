<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\SchemaFacade;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\ConditionGroup;
use Meraki\Schema\Rule\Outcome;
use Meraki\Schema\Rule\OutcomeGroup;

class Rule
{
	public function __construct(
		public ConditionGroup $conditions,
		public OutcomeGroup $outcomes = new OutcomeGroup(),
	) {
	}

	public static function matchAll(): self
	{
		return new self(ConditionGroup::allOf());
	}

	public static function matchAny(): self
	{
		return new self(ConditionGroup::anyOf());
	}

	public static function matchNone(): self
	{
		return new self(ConditionGroup::noneOf());
	}

	/**
	 * Add a condition to the rule.
	 */
	public function when(Condition $condition, Condition ...$conditions): self
	{
		$copy = clone $this;
		$group = $copy->conditions;

		foreach ([$condition, ...$conditions] as $condition) {
			$group = $copy->conditions->add($condition);
		}

		$copy->conditions = $group;

		return $copy;
	}

	/**
	 * Add an outcome to the rule.
	 */
	public function then(Outcome $outcome, Outcome ...$outcomes): self
	{
		$copy = clone $this;
		$group = $copy->outcomes;

		foreach ([$outcome, ...$outcomes] as $outcome) {
			$group = $copy->outcomes->add($outcome);
		}

		$copy->outcomes = $group;

		return $copy;
	}

	/**
	 * Apply the rule to the schema.
	 *
	 * If conditions are met, execute the outcomes.
	 */
	public function apply(array $data, SchemaFacade $schema): bool
	{
		if ($this->evaluate($data, $schema)) {
			$this->execute($data, $schema);
			return true;
		}

		return false;
	}

	/**
	 * Check if the conditions have been met.
	 */
	public function evaluate(array $data, SchemaFacade $schema): bool
	{
		return $this->conditions->evaluate($data, $schema);
	}

	/**
	 * Execute the outcomes regardless of the conditions.
	 */
	public function execute(array $data, SchemaFacade $schema): void
	{
		$this->outcomes->execute($data, $schema);
	}
}
