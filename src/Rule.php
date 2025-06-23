<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Facade;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\ConditionFactory;
use Meraki\Schema\Rule\ConditionGroup;
use Meraki\Schema\Rule\Outcome;
use Meraki\Schema\Rule\OutcomeFactory;

/**
 * @phpstan-import-type SerializedCondition from Condition
 * @phpstan-import-type SerializedOutcome from Outcome
 * @phpstan-type SerializedRule = object{
 * 	when: SerializedCondition,
 * 	then: array<SerializedOutcome>,
 * }
 */
class Rule
{
	public function __construct(
		public readonly ConditionGroup $condition,
		/** @var array<Outcome> */
		public readonly array $outcomes,
	) {
	}

	public function evaluate(Facade $schema, array $data): void
	{
		if ($this->condition->matches($data)) {
			foreach ($this->outcomes as $outcome) {
				$outcome->apply($schema);
			}
		}
	}

	/**
	 * @return SerializedRule
	 */
	public function serialize(): object
	{
		return (object)[
			'when' => $this->condition->serialize(),
			'then' => array_map(
				fn(Outcome $outcome): object => $outcome->serialize(),
				$this->outcomes
			),
		];
	}

	/**
	 * @param SerializedRule $data
	 */
	public static function deserialize(
		object $data,
		ConditionFactory $conditionFactory = new ConditionFactory(),
		OutcomeFactory $outcomeFactory = new OutcomeFactory()
	): static {
		return new self(
			$conditionFactory->deserialize($data->when),
			array_map(
				fn(object $serializedOutcome): Outcome => $outcomeFactory->deserialize($serializedOutcome),
				$data->then
			)
		);
	}
}
