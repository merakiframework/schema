<?php
declare(strict_types=1);

namespace Meraki\Schema;

/**
 * Resolves a scope against one request.
 *
 * A scope addresses one of two things. Most of them address the definition — `min` on a
 * number, `optional` on a field — which no request changes, so resolving those is a plain
 * read of the schema. The exception is `#/fields/<name>/value`, which asks what a field
 * was actually given.
 *
 * That question used to be answered by reading a value staged onto the field, which meant
 * a rule condition could only work if the request had first been written into the shared
 * definition. Reading it from the request instead is what lets the definition stay
 * untouched: two requests can evaluate the same rule at once, and neither leaves anything
 * behind. See docs/LIMITATIONS.md#b7.
 */
final class ScopeResolver
{
	/**
	 * @param array<string, mixed> $given one request's data, by field name
	 */
	public function __construct(
		private readonly Facade $schema,
		private readonly array $given,
	) {
	}

	/**
	 * The value a scope points at, for this request.
	 */
	public function resolve(Scope $scope): mixed
	{
		$field = $this->fieldWhoseValueIsAddressedBy($scope);

		if ($field === null) {
			return $scope->resolve($this->schema)->value;
		}

		// The deprecated input() path stages the request onto the fields and may then
		// apply rules without repeating the data, so a field that was given a value
		// directly is still the authority on its own. Nothing in resolve()/validate()
		// takes this branch; it goes when input() does.
		if ($field->inputGiven) {
			return $field->resolvedValue;
		}

		return $field->resolvedValueFor($this->given[(string) $field->name] ?? null);
	}

	/**
	 * The field whose submitted value this scope addresses, or null when it addresses
	 * anything else — a definition property, or a field itself.
	 */
	private function fieldWhoseValueIsAddressedBy(Scope $scope): ?Field
	{
		$segments = $scope->segments;

		if (count($segments) !== 3 || $segments[0] !== 'fields' || $segments[2] !== 'value') {
			return null;
		}

		return $this->schema->fields->findByName($segments[1]);
	}
}
