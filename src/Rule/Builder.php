<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\ConditionGroup;
use Meraki\Schema\Rule;
use Meraki\Schema\Facade;

final class Builder extends Rule
{
	private ConditionGroup $rootGroup;
	private ConditionGroup $currentGroup;

	/** @var Outcome[] */
	private array $outcomesToAdd = [];

	protected function __construct(ConditionGroup $rootGroup)
	{
		$this->rootGroup = $rootGroup;
		$this->currentGroup = $this->rootGroup;
	}

	public static function whenAllOf(): self
	{
		return new self(new Condition\AllOf());
	}

	public static function whenAnyOf(): self
	{
		return new self(new Condition\AnyOf());
	}

	public function when(Condition $condition): self
	{
		$this->currentGroup->add($condition);

		return $this;
	}

	public function andWhen(Condition $condition): self
	{
		if ($this->currentGroup instanceof Condition\AllOf) {
			$this->currentGroup->add($condition);

			return $this;
		}

		$this->currentGroup = new Condition\AllOf($this->currentGroup, $condition);
		$this->rootGroup = $this->currentGroup;

		return $this;
	}

	public function orWhen(Condition $condition): self
	{
		if ($this->currentGroup instanceof Condition\AnyOf) {
			$this->currentGroup->add($condition);

			return $this;
		}

		$this->currentGroup = new Condition\AnyOf($this->currentGroup, $condition);
		$this->rootGroup = $this->currentGroup;

		return $this;
	}

	public function whenEquals(string $target, mixed $expected): self
	{
		return $this->when(new Condition\Equals($target, $expected));
	}

	public function andWhenEquals(string $target, mixed $expected): self
	{
		return $this->andWhen(new Condition\Equals($target, $expected));
	}

	public function orWhenEquals(string $target, mixed $expected): self
	{
		return $this->orWhen(new Condition\Equals($target, $expected));
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
		return new Rule($this->rootGroup, $this->outcomesToAdd);
	}

	public function evaluate(Facade $schema, array $data): void
	{
		$this->build()->evaluate($schema, $data);
	}
}
