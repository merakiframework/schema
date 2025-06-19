<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Rule\Condition;
use InvalidArgumentException;

/**
 * @phpstan-import-type SerializedCondition from Condition
 * @template T of SerializedCondition
 */
final class ConditionFactory
{
	public function __construct(
		/** @var array<string, class-string<Condition>> */
		private array $conditionMap = [
			'all_of' => Condition\AllOf::class,
			'any_of' => Condition\AnyOf::class,
			'equals' => Condition\Equals::class,
		],
	) {
	}

	public function allOf(Condition ...$conditions): Condition\AllOf
	{
		return new Condition\AllOf(...$conditions);
	}

	public function anyOf(Condition ...$conditions): Condition\AnyOf
	{
		return new Condition\AnyOf(...$conditions);
	}

	public function equals(string $scope, mixed $value): Condition\Equals
	{
		return new Condition\Equals($scope, $value);
	}

	/**
	 * @param T $data
	 */
	public function deserialize(object $data): Condition
	{
		if (!isset($this->conditionMap[$data->type])) {
			throw new InvalidArgumentException('Unknown condition type: ' . $data->action);
		}

		$class = $this->conditionMap[$data->type];

		if ($this->requiresConditionFactory($class)) {
			return $class::deserialize($data, $this);
		}

		return $class::deserialize($data);
	}

	private function requiresConditionFactory(string $class): bool
	{
		$reflection = new \ReflectionClass($class);
		$params = $reflection->getMethod('deserialize')->getParameters();

		foreach ($params as $param) {
			if ($param->getType()->__tostring() === ConditionFactory::class) {
				return true;
			}
		}

		return false;
	}
}
