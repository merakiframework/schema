<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Outcome;

use Meraki\Schema\Exception\InvalidRule;
use Meraki\Schema\Field;
use Meraki\Schema\Rule\Outcome;
use Meraki\Schema\FieldScope;
use Meraki\Schema\Scope;

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
			throw InvalidRule::outcomeDoesNotAddressAField(self::class, $field);
		}

		$this->scope = $scope;
	}

	public function applyTo(Field $field): Field
	{
		// The definition is untouched, so the field comes back exactly as it went in.
		// "Ignore this field" is a statement about one request, not about the field, so it is
		// honoured where the request is: Facade::against() sees this outcome among the ones
		// that were applied and withholds the submitted value.
		//
		// It used to set a flag on the field instead, which meant a schema remembered —
		// between requests, and for everyone — that some earlier request's value had been
		// discarded.
		return $field;
	}

	public function getScope(): FieldScope
	{
		return $this->scope;
	}
}
