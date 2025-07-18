<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Meraki\Schema\Facade;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\ConditionGroup;
use Meraki\Schema\Rule\ConditionFactory;
use InvalidArgumentException;

/**
 * @phpstan-import-type SerializedCondition from Condition
 * @phpstan-type SerializedAllOf = SerializedCondition&object{
 * 	type: 'all_of',
 * 	conditions: array<SerializedCondition>
 * }
 * @implements ConditionGroup<SerializedAllOf>
 */
final class AllOf implements ConditionGroup
{
	/** @var Condition[] */
	private array $conditions;

	public function __construct(Condition ...$conditions)
	{
		$this->conditions = $conditions;
	}

	public function matches(array $data, Facade $schema): bool {
		foreach ($this->conditions as $condition) {
			if (!$condition->matches($data, $schema)) {
				return false;
			}
		}
		return true;
	}

	public function add(Condition $condition): static
	{
		$this->conditions[] = $condition;
		return $this;
	}

	public function getScopes(): array
	{
		$scopes = [];
		foreach ($this->conditions as $condition) {
			$scopes = array_merge($scopes, $condition->getScopes());
		}
		return $scopes;
	}

	/**
	 * @return SerializedAllOf
	 */
	public function serialize(): object
	{
		return (object)[
			'type' => 'all_of',
			'conditions' => array_map(
				fn(Condition $condition): object => $condition->serialize(),
				$this->conditions
			)
		];
	}

	/**
	 * @param SerializedAllOf $data
	 */
	public static function deserialize(object $data, ?ConditionFactory $conditionFactory = new ConditionFactory()): static
	{
		if ($data->type !== 'all_of') {
			throw new InvalidArgumentException('Invalid serialized condition type: ' . $data->type);
		}

		$conditions = [];

		foreach ($data->conditions as $conditionData) {
			$conditions[] = $conditionFactory->deserialize($conditionData);
		}

		return new self(...$conditions);
	}
}
