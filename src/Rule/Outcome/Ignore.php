<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Outcome;

use Meraki\Schema\Rule\Outcome;
use Meraki\Schema\Scope;
use Meraki\Schema\Facade;
use Meraki\Schema\Field;
use InvalidArgumentException;

final class Ignore implements Outcome
{
	private Scope $scope;

	public function __construct(public readonly string $field)
	{
		$this->scope = new Scope($this->field);
	}

	public function apply(Facade $schema): void
	{
		$target = $this->scope->resolve($schema)->value;

		if ($target instanceof Field) {
			$target->ignoreInput();

			return;
		}

		throw new InvalidArgumentException("Ignore can only be applied to fields.");
	}

	public function getScope(): Scope
	{
		return $this->scope;
	}
}
