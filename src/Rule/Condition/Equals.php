<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Meraki\Schema\Facade;
use Meraki\Schema\Property;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Scope;
use InvalidArgumentException;

final class Equals implements Condition
{
	public readonly string $target;

	public Scope $scope;
	public mixed $expected;

	public function __construct(string $target, mixed $expected)
	{
		$this->target = $target;
		$this->scope = new Scope($target);
		$this->expected = $expected;
	}

	public function matches(array $data, Facade $schema): bool
	{
		$value = $this->scope->resolve($schema)->value;

		if ($value instanceof Property) {
			$value = $value->value;
		}

		return $value === $this->expected;
	}

	public function getScopes(): array
	{
		return [$this->scope];
	}
}
