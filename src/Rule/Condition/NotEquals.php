<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Meraki\Schema\Facade;
use Meraki\Schema\Property;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Scope;
use Meraki\Schema\ScopeResolver;

/**
 * Holds when what the scope points at is not the expected value.
 *
 * Unlike an outcome, this accepts any kind of scope: comparing a submitted value
 * (`#/fields/plan/value`) and comparing part of the definition (`#/fields/age/min`) are
 * both meaningful questions to ask.
 */
final class NotEquals implements Condition
{
	public readonly Scope $scope;

	/**
	 * The scope in its string form, which is what `meraki/schema-json` writes to disk.
	 * Derived rather than stored, so it cannot drift from the scope it describes.
	 */
	public string $target {
		get => (string) $this->scope;
	}

	public function __construct(Scope|string $target, public readonly mixed $expected)
	{
		$this->scope = $target instanceof Scope ? $target : Scope::parse($target);
	}

	public function matches(array $data, Facade $schema): bool
	{
		$value = (new ScopeResolver($schema, $data))->resolve($this->scope);

		if ($value instanceof Property) {
			$value = $value->value;
		}

		return $value !== $this->expected;
	}

	public function getScopes(): array
	{
		return [$this->scope];
	}
}
