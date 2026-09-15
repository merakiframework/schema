<?php
declare(strict_types=1);

namespace Meraki\Schema;

use InvalidArgumentException;

/**
 * Answers what a scope points at, for one request.
 *
 * This is the only place resolution happens. It used to be spread across the objects being
 * addressed: `Facade::traverse()` recognised `fields`, handed a cursor to
 * `Field::traverse()`, and each advanced it. That put path-walking inside the definition —
 * a field had to know about scopes to be readable — and it is how a scope reached
 * `Field::$schema` and climbed back to the root, which was defect B8. A resolver reading a
 * name-keyed set has no parent pointer to follow, so that whole class of problem is gone
 * rather than guarded against.
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
	 * @throws InvalidArgumentException if the scope names a field or property that does
	 *         not exist
	 */
	public function resolve(Scope $scope): mixed
	{
		$field = $this->schema->fields->getByName($scope->field);

		return match (true) {
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
			throw new InvalidArgumentException(sprintf(
				'No property "%s" on field "%s".',
				$property,
				(string) $field->name,
			));
		}

		return $field->{$property};
	}
}
