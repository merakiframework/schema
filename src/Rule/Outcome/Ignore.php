<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Outcome;

use Meraki\Schema\Rule\Outcome;
use Meraki\Schema\Facade;
use Meraki\Schema\FieldScope;
use Meraki\Schema\Scope;
use InvalidArgumentException;

/**
 * Discards whatever was submitted for a field, so it validates as empty.
 *
 * Takes a {@see FieldScope}, so a path addressing a value or a definition property is
 * rejected where the rule is written rather than when a request arrives. Applying it is
 * then a lookup by name: there is no path left to walk.
 */
final class Ignore implements Outcome
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
		// Nothing to do to the definition. "Ignore this field" is a statement about one
		// request, so it is honoured where the request is: Facade::against() sees this
		// outcome among the ones that were applied and withholds the submitted value. It
		// used to set a flag on the field, which meant a schema remembered, between
		// requests, that some earlier request's value had been discarded.
	}

	public function getScope(): FieldScope
	{
		return $this->scope;
	}
}
