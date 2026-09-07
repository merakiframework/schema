<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Outcome;

use Meraki\Schema\Rule\Outcome;
use Meraki\Schema\Facade;
use Meraki\Schema\FieldScope;
use Meraki\Schema\Scope;
use InvalidArgumentException;

/**
 * Requires a field, whatever the author declared.
 *
 * Takes a {@see FieldScope}, so a path addressing a value or a definition property is
 * rejected where the rule is written rather than when a request arrives. Applying it is
 * then a lookup by name: there is no path left to walk.
 */
final class _Require implements Outcome
{
	private readonly FieldScope $scope;

	public function __construct(public readonly string $field)
	{
		$scope = Scope::parse($field);

		if (!$scope instanceof FieldScope) {
			throw new InvalidArgumentException(sprintf(
				'%s applies to a field, but "%s" addresses something else. Drop the trailing segment.',
				self::class,
				$field,
			));
		}

		$this->scope = $scope;
	}

	public function apply(Facade $schema): void
	{
		$schema->fields->getByName($this->scope->field)->require();
	}

	public function getScope(): FieldScope
	{
		return $this->scope;
	}
}
