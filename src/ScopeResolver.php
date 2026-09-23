<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Exception\InvalidScope;
use Meraki\Schema\Exception\UnknownField;

/**
 * Answers what a scope points at, for one request.
 *
 * Only {@see ValueScope} depends on the request. The other kinds read the definition,
 * which is the same for every request and is never written to here.
 */
final class ScopeResolver
{
	/**
	 * @param array<string, mixed> $given one request's data, by field name
	 */
	public function __construct(
		private readonly Facade $schema,
		private readonly array $given = [],
	) {
	}

	/**
	 * @throws InvalidScope if the scope names a property the field does not have
	 * @throws UnknownField if it names a field the schema does not hold
	 */
	public function resolve(Scope $scope): mixed
	{
		$field = $this->schema->fields->getByName($scope->field);

		return match (true) {
			$scope instanceof PartScope => $this->partOf($field, $scope->part),
			$scope instanceof ValueScope => $this->valueOf($field),
			$scope instanceof PropertyScope => $this->propertyOf($field, $scope->property),
			default => $field,
		};
	}

	/**
	 * What the field was given, or its authored default when the request said nothing.
	 */
	private function valueOf(Field $field): mixed
	{
		return $field->resolvedValueFor($this->given[(string) $field->name] ?? null);
	}

	/**
	 * One named part of what the field was given.
	 *
	 * The part *name* is checked against the value class rather than against a value, so a
	 * mistyped part fails where the rule is written instead of resolving to `null` on every
	 * request afterwards — which is the failure this library spends most of its guards avoiding,
	 * and which is invisible precisely because `null` is a legitimate answer for a part nobody
	 * filled in.
	 *
	 * @throws InvalidScope if the field's value has no parts, or not that one
	 */
	private function partOf(Field $field, string $part): mixed
	{
		$parts = Field\ValueClass::partNamesOf($field);

		if ($parts === []) {
			throw InvalidScope::fieldHoldsNoParts((string) $field->name, $part);
		}

		if (!in_array($part, $parts, true)) {
			throw InvalidScope::fieldHasNoSuchPart((string) $field->name, $part, $parts);
		}

		$value = $this->valueOf($field);

		// Nothing was submitted, so every part of it is absent. Not an error: a rule asking
		// "is the shipping country the billing country" on a request that gave neither is
		// answerable, and the answer is that they are both nothing.
		return $value instanceof Field\HasParts ? ($value->parts()[$part] ?? null) : null;
	}

	/**
	 * Every public property of a field is addressable, with no exceptions list.
	 *
	 * There used to be one — `Field::NOT_ADDRESSABLE`, holding `schema` — because a field
	 * carried a back-reference to its owner, and a scope stepping into it climbed to the root
	 * and walked forever (defect B8). The back-reference is gone, so the guard has nothing left
	 * to name. A field's public properties really are its whole API now.
	 */
	private function propertyOf(Field $field, string $property): mixed
	{
		if (!property_exists($field, $property)) {
			throw InvalidScope::fieldHasNoSuchProperty((string) $field->name, $property);
		}

		return $field->{$property};
	}
}
