<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\Outcome;
use Meraki\Schema\Facade;
use Meraki\Schema\Rule;

final class Builder extends Rule
{
	private Condition $conditionGroup;

	private array $outcomesToAdd = [];

	public function __construct(Condition $group)
	{
		$this->conditionGroup = $group;
	}

	public function whenAllOf(Condition ...$conditions): self
	{
		$this->conditionGroup = new Condition\AllOf(...$conditions);
		return $this;
	}

	public function whenAnyOf(Condition ...$conditions): self
	{
		$this->conditionGroup = new Condition\AnyOf(...$conditions);
		return $this;
	}

	public function whenEquals(string $target, mixed $expected): self
	{
		$this->conditionGroup = new Condition\Equals($target, $expected);
		return $this;
	}

	public function then(Outcome ...$outcomes): self
	{
		$this->outcomesToAdd = array_merge($this->outcomesToAdd, $outcomes);
		return $this;
	}

	public function thenMakeOptional(string $scope): self
	{
		return $this->then(new Outcome\MakeOptional($scope));
	}

	public function build(): Rule
	{
		return new Rule($this->conditionGroup, $this->outcomesToAdd);
	}

	public function evaluate(Facade $schema, array $data): void
	{
		$this->build()->evaluate($schema, $data);
	}
}
