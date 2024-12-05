<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Rule\ConditionGroup;
use Meraki\Schema\Attribute;
use Meraki\Schema\SchemaFacade;


class Condition
{
	public function __construct(
		public Attribute $target,
		public Attribute $operator,
		public Attribute $expected,
	) {
	}

	public function evaluate(array $data, SchemaFacade $schema): bool
	{
		$target = $this->target->value;
		$operator = $this->operator->value;
		$expected = $this->expected->value;

		if ($this->target->isScoped()) {
			$scope = $this->target->getScope();
			$target = $scope->resolve($schema);
		}

		$actual = $target;

		return match ($operator) {
			'equals' => $actual === $expected,
			'not_equals' => $actual !== $expected,
			'contains' => strpos($actual, $expected) !== false,
			'does_not_contain' => strpos($actual, $expected) === false,
			default => false,
		};
	}

	public static function create(string $target, string $operator, mixed $expected): self
	{
		return new self(new Attribute('target', $target), new Attribute('operator', $operator), new Attribute('expected', $expected));
	}

	public static function matchAll(Condition ...$conditions): ConditionGroup
	{
		return ConditionGroup::allOf(...$conditions);
	}

	public static function matchAny(Condition ...$conditions): ConditionGroup
	{
		return ConditionGroup::anyOf(...$conditions);
	}

	public static function matchNone(Condition ...$conditions): ConditionGroup
	{
		return ConditionGroup::noneOf(...$conditions);
	}

	public function __toObject(): object
	{
		return (object) $this->__toArray();
	}

	public function __toArray(): array
	{
		return [
			'target' => $this->target->value,
			'operator' => $this->operator->value,
			'expected' => $this->expected->value
		];
	}
}
