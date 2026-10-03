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
	 * A **field set**, not a schema. Resolving only ever needs to look a field up by name, and
	 * taking the smaller thing is what lets a set that is not a schema's — a collection's
	 * template, resolving one row — be resolved by this same class rather than by a second copy
	 * of it.
	 *
	 * @param array<string, mixed> $given one request's data, by field name
	 */
	public function __construct(
		private readonly Field\Set $fields,
		private readonly array $given = [],
	) {
	}

	/**
	 * @throws InvalidScope if the scope names a property the field does not have
	 * @throws UnknownField if it names a field the schema does not hold
	 */
	public function resolve(Scope $scope): mixed
	{
		// A column is the same question asked of every row, so it is answered by asking it — once
		// per row, through this same method. That is the whole benefit of a scope being a locator
		// and a tail: no tail has to learn what a list is.
		if ($scope->in instanceof Scope\Column) {
			return $this->acrossEveryRow($scope, $scope->in);
		}

		$field = $this->fieldIn($scope->in);

		return match (true) {
			$scope instanceof PartScope => $this->partOf($field, $scope),
			$scope instanceof ValueScope => $this->valueIn($scope->in, $field),
			$scope instanceof PropertyScope => $this->propertyOf($field, $scope->property),
			default => $field,
		};
	}

	/**
	 * The field a scope is *about*, or null when the schema cannot offer one.
	 *
	 * Not the same as looking `$scope->field` up in the set: that answers the collection for
	 * anything reaching into one, and a rule asking whether a field could hold a value has to ask
	 * the field that would hold it. `#/fields/lines/value/rush/sku/value` is about `sku`, and
	 * comparing it against `'URGENT'` is a question for `Text`, not for `Collection`.
	 *
	 * Null rather than raising, because every caller is a check that runs *after*
	 * {@see Definition::addRule()} has already reported an unaddressable scope in better words.
	 */
	public function fieldFor(Scope $scope): ?Field
	{
		try {
			return $this->fieldIn($scope->in);
		} catch (InvalidScope | UnknownField) {
			return null;
		}
	}

	/**
	 * What a part scope reads its part from, or null when there is nothing to read.
	 *
	 * The field's {@see Field\Input}: the parts as read, whether or not they make a value, so a
	 * rule about the country holds on an address whose street is still empty. A field whose value
	 * is still read in one step has no input, and its value is asked instead.
	 *
	 * Public because a comparison needs the same thing a part is read from to canonicalise what
	 * the rule was written with: an address stores `AU-QLD` for `QLD`, and only the thing holding
	 * the country can say so. Null for a column, which is a part of every row rather than of one
	 * thing.
	 *
	 * @throws InvalidScope if the scope reaches into a template without naming a row
	 */
	public function partsHolderFor(PartScope $scope): ?Field\HasParts
	{
		if ($scope->in instanceof Scope\Column) {
			return null;
		}

		$field = $this->fieldIn($scope->in);
		$input = $this->inputIn($scope->in, $field);

		if ($input !== null) {
			return $input;
		}

		$value = $this->valueIn($scope->in, $field);

		return $value instanceof Field\HasParts ? $value : null;
	}

	/**
	 * The names of the rows a collection was given, in the order they arrived.
	 *
	 * Empty when nothing usable was submitted, which is not an error — {@see Rule\Condition\Quantified}
	 * folds over these, and "no rows" is a request it has to be able to answer about.
	 *
	 * @return list<string>
	 */
	public function rowNamesIn(FieldName $collection): array
	{
		return $this->rowsOf($collection)?->keys() ?? [];
	}

	/**
	 * The field a locator is about — the schema's own, or one of a collection's template fields.
	 *
	 * Everything checkable without a request is checked here, which is what makes a scope typo an
	 * error where the rule is *written*: that the field is a collection at all, and that its
	 * template really holds the field being named.
	 *
	 * @throws InvalidScope if the locator reaches into something that is not a collection, or names
	 *         a template field that is not there
	 * @throws UnknownField if it names a field the schema does not hold
	 */
	private function fieldIn(Scope\Locator $in): Field
	{
		$field = $this->fields->getByName($in->field);

		if ($in instanceof Scope\SchemaField) {
			return $field;
		}

		if (!$field instanceof Field\Collection) {
			throw InvalidScope::fieldIsNotACollection((string) $in->field, $field::class);
		}

		return self::templateFieldOf($field, $in->addresses());
	}

	/**
	 * What was given for the field a locator is about.
	 *
	 * @throws InvalidScope if a template value is asked for outside a row
	 */
	private function valueIn(Scope\Locator $in, Field $field): mixed
	{
		return match (true) {
			$in instanceof Scope\Row => $this->rowsOf($in->field)?->valueOf($in->row, (string) $in->addresses()),
			// The definition is row-agnostic; a value is not. Guessing between "the first row" and
			// "all of them" would answer a question nobody asked — `*` is how you ask about every
			// row, and a rule applied per row binds this to the row it is validating.
			$in instanceof Scope\Template => throw InvalidScope::aTemplateValueNeedsARow((string) $in->field, (string) $in->addresses()),
			default => $this->valueOf($field),
		};
	}

	/**
	 * What the field a locator is about read its parts as, or null when there is no such reading.
	 *
	 * A row is read the way the collection reads it, from the row as it was submitted: a row
	 * the collection could not use, or one that is not there, has no parts to offer.
	 *
	 * @throws InvalidScope if a template value is asked for outside a row
	 */
	private function inputIn(Scope\Locator $in, Field $field): ?Field\Input
	{
		if ($in instanceof Scope\Template) {
			throw InvalidScope::aTemplateValueNeedsARow((string) $in->field, (string) $in->addresses());
		}

		if (!$in instanceof Scope\Row) {
			return $field->resolvedInputFor($this->given[(string) $field->name] ?? null);
		}

		$row = ($this->rowsOf($in->field)?->has($in->row) ?? false) ? $this->submittedRow($in) : null;

		return $row === null ? null : $field->resolvedInputFor($row[(string) $in->addresses()] ?? null);
	}

	/**
	 * One row as it was submitted, or as the collection's default holds it — a record of raw
	 * values by template field — or null when it is not a record.
	 *
	 * @return array<string, mixed>|null
	 */
	private function submittedRow(Scope\Row $in): ?array
	{
		$rows = $this->given[(string) $in->field] ?? $this->fields->getByName($in->field)->defaultValue;
		$row = is_array($rows) ? ($rows[$in->row] ?? null) : null;

		// From outside the object, so only its public properties: a row is a record, and that is
		// how every record is read — see Field\Definition::recordIn().
		return is_object($row) ? get_object_vars($row) : null;
	}

	/**
	 * The same tail, asked of every row, under the names the rows arrived with.
	 *
	 * @return array<string, mixed>
	 */
	private function acrossEveryRow(Scope $scope, Scope\Column $column): array
	{
		// Reached for the template check even when there are no rows, so `*` naming a field the
		// template does not have still fails where the rule is written rather than answering `[]`.
		$this->fieldIn($column);

		$values = [];

		foreach ($this->rowsOf($column->field)?->keys() ?? [] as $row) {
			$in = new Scope\Row($column->field, $row, $column->addresses());
			$values[$row] = $this->resolve($scope->rootedAt($in));
		}

		return $values;
	}

	/**
	 * The rows of a collection, as it parsed them — or null when nothing usable was submitted.
	 *
	 * Absence is not an error. Which rows exist is a fact about a request, and a scope is written
	 * long before one arrives, so a row that is not there resolves to nothing exactly as an
	 * unfilled part of an address does.
	 */
	private function rowsOf(FieldName $collection): ?Field\Collection\Value
	{
		$rows = $this->valueOf($this->fields->getByName($collection));

		return $rows instanceof Field\Collection\Value ? $rows : null;
	}

	/**
	 * @throws InvalidScope if the template does not hold that field
	 */
	private static function templateFieldOf(Field\Collection $collection, FieldName $named): Field
	{
		foreach ($collection->template as $field) {
			if ($field->name->equals($named)) {
				return $field;
			}
		}

		throw InvalidScope::collectionHasNoSuchTemplateField(
			(string) $collection->name,
			(string) $named,
			array_map(static fn(Field $f): string => (string) $f->name, $collection->template),
		);
	}

	/**
	 * What the field was given, or its authored default when the request said nothing.
	 */
	private function valueOf(Field $field): mixed
	{
		return $field->resolvedValueFor($this->given[(string) $field->name] ?? null);
	}

	/**
	 * One named part of what the field was given, as read — whether or not the parts make a
	 * value, so a rule about one part answers while the form is half-filled.
	 *
	 * The part *name* is checked against the field's declared parts rather than against a value,
	 * so a mistyped part fails where the rule is written instead of resolving to `null` on every
	 * request afterwards — which is the failure this library spends most of its guards avoiding,
	 * and which is invisible precisely because `null` is a legitimate answer for a part nobody
	 * filled in.
	 *
	 * @throws InvalidScope if the field's value has no parts, or not that one
	 */
	private function partOf(Field $field, PartScope $scope): mixed
	{
		$parts = array_column($field->parts, 'value');

		if ($parts === []) {
			throw InvalidScope::fieldHoldsNoParts((string) $field->name, $scope->part);
		}

		if (!in_array($scope->part, $parts, true)) {
			throw InvalidScope::fieldHasNoSuchPart((string) $field->name, $scope->part, $parts);
		}

		// Nothing was submitted, so every part of it is absent. Not an error: a rule asking
		// "is the shipping country the billing country" on a request that gave neither is
		// answerable, and the answer is that they are both nothing.
		return $this->partsHolderFor($scope)?->parts()[$scope->part] ?? null;
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
