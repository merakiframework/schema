<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Meraki\Schema\Field;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\ConditionGroup;

final readonly class AnyOf implements ConditionGroup
{
	/** @var list<Condition> */
	private array $conditions;

	public function __construct(Condition ...$conditions)
	{
		$this->conditions = $conditions;
	}

	public function matches(array $data, Field\Set $fields): bool
	{
		foreach ($this->conditions as $condition) {
			if ($condition->matches($data, $fields)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return list<Condition>
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
