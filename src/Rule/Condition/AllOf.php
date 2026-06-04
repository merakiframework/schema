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
		// An empty group should not fire a rule (matches AnyOf's behaviour),
		// rather than being vacuously true and always matching.
		if ($this->conditions === []) {
			return false;
		}

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

	/**
	 * @return Condition[]
	 */
	public function conditions(): array
	{
		return $this->conditions;
	}

	public function getScopes(): array
	{
		$scopes = [];
		foreach ($this->conditions as $condition) {
			$scopes = array_merge($scopes, $condition->getScopes());
		}
		return $scopes;
	}
}
