<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Rule;
use Meraki\Schema\Rule\ConditionGroup;
use Meraki\Schema\Rule\Outcome;
use Meraki\Schema\Rule\Condition;

class Printer
{
	public function __construct(
		private int $level = 0,
	) {

	}

	public function print(Rule $rule): string
	{
		$str = "Rule:" . PHP_EOL;

		$this->incrementLevel();

		$str .= $this->printConditions($rule->conditions);
		$str .= $this->printOutcomes($rule->outcomes);

		$this->decrementLevel();

		return $str;
	}

	private function incrementLevel(): void
	{
		$this->level++;
	}

	private function decrementLevel(): void
	{
		$this->level--;
	}

	private function printLevel(?int $level = null): string
	{
		return str_repeat("\t", $level ?? $this->level);
	}

	private function printConditions(ConditionGroup $conditions): string
	{
		$header = $this->printLevel();

		$header .= "Conditions:" . PHP_EOL;

		$this->incrementLevel();

		$out = $header . $this->printCondition($conditions);

		$this->decrementLevel();

		return $out;
	}

	private function printOutcomes(OutcomeGroup $outcomes): string
	{
		$header = $this->printLevel();

		$this->incrementLevel();

		$header .= "Outcomes:" . PHP_EOL;

		$outcomeStrings = array_map(
			fn(Outcome $outcome): string => $this->printOutcome($outcome),
			$outcomes->__toArray()
		);

		$this->decrementLevel();

		return $header . implode(PHP_EOL, $outcomeStrings);
	}

	private function printCondition(Condition|ConditionGroup $conditionOrConditionGroup): string
	{
		if ($conditionOrConditionGroup instanceof ConditionGroup) {
			return $this->printConditionGroup($conditionOrConditionGroup);
		}

		return $this->printSingleCondition($conditionOrConditionGroup);
	}

	private function printSingleCondition(Condition $condition): string
	{
		$conditionString = $this->printLevel()
			. $condition->target->value
			. ' '
			. $condition->operator->value
			. ' '
			. $condition->expected->value
			. PHP_EOL;

		return $conditionString;
	}

	private function printConditionGroup(ConditionGroup $conditions): string
	{
		$out = $this->printLevel() . $conditions->type . ':' . PHP_EOL;

		$this->incrementLevel();

		foreach ($conditions as $condition) {
			$out .= $this->printCondition($condition);
		}

		$this->decrementLevel();

		return $out . PHP_EOL;
	}

	private function printOutcome(Outcome $outcome): string
	{
		$outcomeString = str_repeat("\t", $this->level) . $outcome->action->name . ' => ' . $outcome->action->value . PHP_EOL;
		$outcomeString .= str_repeat("\t", $this->level) . $outcome->target->name . ' => ' . $outcome->target->value . PHP_EOL;

		// if ($outcome->otherAttributes) {
		// 	$this->level++;
		// 	$outcomeString .= PHP_EOL.$this->printAttributes($outcome->otherAttributes);
		// 	$this->level--;
		// }

		return $outcomeString;
	}
}
