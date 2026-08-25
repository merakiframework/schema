<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\ResolvedField;
use Meraki\Schema\AggregatedValidationResult;
use Meraki\Schema\Property;
use InvalidArgumentException;

/**
 * A repeatable list of items, where each item is a group of atomic fields (the
 * "template", supplied to the inherited {@see Composite} constructor). Resolves to a
 * list of item arrays, each keyed by the template's local sub-field names — e.g.
 * `lessons => [['date' => …, 'time' => …], …]`.
 *
 * Each item is validated individually against the template; `minItems`/`maxItems`
 * bound the list length.
 *
 * @extends Composite<list<array<string, mixed>>>
 */
final class Collection extends Composite
{
	public private(set) int $minItems = 0;
	public private(set) ?int $maxItems = null;

	public function minItems(int $count): static
	{
		$this->minItems = $count;
		return $this;
	}

	public function maxItems(int $count): static
	{
		$this->maxItems = $count;
		return $this;
	}

	public function validateValue(mixed $value): bool
	{
		return is_array($value);
	}

	protected function valueProvided(Property\Value $value): bool
	{
		$items = $value->unwrap();

		// Input that was never a list is still input. Treating it as absent would fall
		// back to the default and quietly validate an empty list instead of reporting it.
		if (!is_array($items)) {
			return $items !== null;
		}

		return $items !== [];
	}

	/**
	 * A collection holds a *list*, so (unlike a fixed composite) the value is not
	 * mapped onto the template fields here; per-item mapping happens in validate().
	 *
	 * @param list<array<string, mixed>>|null $value
	 */
	public function input($value): static
	{
		$this->inputGiven = true;
		$this->value = $this->process($value);
		$this->resolveValue();

		return $this;
	}

	/**
	 * @param list<array<string, mixed>>|null $value
	 */
	public function prefill($value): static
	{
		$this->defaultValue = $this->process($value);
		$this->resolveValue();

		return $this;
	}

	/**
	 * Resolves each item against the template, without writing to it.
	 *
	 * The old path fed each item into the template fields with `input()`, so after
	 * validating a list the template held the *last* item's values. Nothing is written
	 * here, so the same template can resolve every item independently.
	 *
	 * @param list<\Meraki\Schema\Rule\AppliedOutcome> $appliedOutcomes
	 */
	public function resolve(mixed $given, array $appliedOutcomes = []): CompositeValidationResult
	{
		$items = $this->resolvedValueFor($given)->unwrap();
		$results = [new ResolvedField($this, $given, $items, $appliedOutcomes)];

		foreach (is_array($items) ? $items : [] as $item) {
			foreach ($this->resolveItem($item) as $leaf) {
				$results[] = $leaf;
			}
		}

		return new CompositeValidationResult($this, ...$results);
	}

	/**
	 * @param list<\Meraki\Schema\Rule\AppliedOutcome> $appliedOutcomes
	 */
	public function validate(mixed $given, array $appliedOutcomes = []): CompositeValidationResult
	{
		$value = $this->resolvedValueFor($given);
		$items = $value->unwrap();
		$own = new ResolvedField($this, $given, $items, $appliedOutcomes);

		// Input that was never a list fails the shape check, and the count constraints
		// have nothing to count, so they are skipped rather than failed.
		if (!$this->validateValue($items)) {
			$results = [ConstraintValidationResult::fail('type')];

			foreach (array_keys($this->getConstraints()) as $name) {
				$results[] = ConstraintValidationResult::skip($name);
			}

			return new CompositeValidationResult($this, $own->withResults(...$results));
		}

		$results = [ConstraintValidationResult::pass('type')];

		if ($this->optional && $items === []) {
			foreach (array_keys($this->getConstraints()) as $name) {
				$results[] = ConstraintValidationResult::skip($name);
			}
		} else {
			foreach ($this->evaluateConstraints($value) as $name => $passed) {
				$results[] = match ($passed) {
					true => ConstraintValidationResult::pass($name),
					false => ConstraintValidationResult::fail($name),
					default => ConstraintValidationResult::skip($name),
				};
			}
		}

		$all = [$own->withResults(...$results)];

		foreach ($items as $item) {
			foreach ($this->validateItem($item) as $leaf) {
				$all[] = $leaf;
			}
		}

		return new CompositeValidationResult($this, ...$all);
	}

	/**
	 * @return list<ResolvedField>
	 */
	private function resolveItem(mixed $item): array
	{
		return $this->perTemplateField($item, static fn(Field $f, mixed $v): AggregatedValidationResult => $f->resolve($v));
	}

	/**
	 * @return list<ResolvedField>
	 */
	private function validateItem(mixed $item): array
	{
		return $this->perTemplateField($item, static fn(Field $f, mixed $v): AggregatedValidationResult => $f->validate($v));
	}

	/**
	 * @param callable(Field, mixed): AggregatedValidationResult $each
	 * @return list<ResolvedField>
	 */
	private function perTemplateField(mixed $item, callable $each): array
	{
		$item = is_array($item) ? $item : [];
		$out = [];

		foreach ($this->fields as $field) {
			$local = (string) $field->name->removePrefix();

			// A sub-field that is itself structured (a per-item address) returns an
			// aggregate; flatten it to the leaves this result accepts.
			foreach ($this->flattenResolved($each($field, $item[$local] ?? null)) as $leaf) {
				$out[] = $leaf;
			}
		}

		return $out;
	}

	/**
	 * @return list<ResolvedField>
	 */
	private function flattenResolved(AggregatedValidationResult $result): array
	{
		if ($result instanceof ResolvedField) {
			return [$result];
		}

		$flat = [];

		foreach ($result as $child) {
			foreach ($this->flattenResolved($child) as $leaf) {
				$flat[] = $leaf;
			}
		}

		return $flat;
	}

	/**
	 * Flattens a sub-field's validation result into the per-leaf {@see ValidationResult}s
	 * that {@see CompositeValidationResult} accepts, recursing through nested composites.
	 *
	 * @return list<ValidationResult>
	 */
	private function flattenResults(ValidationResult|CompositeValidationResult $result): array
	{
		if (!$result instanceof CompositeValidationResult) {
			return [$result];
		}

		$flat = [];

		foreach ($result as $child) {
			foreach ($this->flattenResults($child) as $leaf) {
				$flat[] = $leaf;
			}
		}

		return $flat;
	}

	protected function getConstraints(): array
	{
		$constraints = [];

		if ($this->minItems > 0) {
			$constraints['minItems'] = fn(mixed $v): ?bool => is_array($v) ? count($v) >= $this->minItems : null;
		}

		if ($this->maxItems !== null) {
			$constraints['maxItems'] = fn(mixed $v): ?bool => is_array($v) ? count($v) <= $this->maxItems : null;
		}

		return $constraints;
	}

	/**
	 * Normalises the incoming value to a list of item arrays, each keyed by the
	 * template's local sub-field names.
	 *
	 * @param mixed $value
	 */
	protected function process($value): Property\Value
	{
		if ($value === null) {
			return new Property\Value([]);
		}

		if (is_object($value)) {
			$value = get_object_vars($value);
		}

		// Input that is not a list cannot be split into items. Keep it as it came rather
		// than raising: form input is attacker-controlled, so this has to surface as a
		// validation failure and not as a fatal. validate() reports it against the field.
		if (!is_array($value)) {
			return new Property\Value($value);
		}

		$items = [];

		foreach (array_values($value) as $rawItem) {
			if (is_object($rawItem)) {
				$rawItem = get_object_vars($rawItem);
			}

			// An item that is not a set of field values is kept as it came, for the same
			// reason. Dropping it here would silently shorten the list instead.
			if (!is_array($rawItem)) {
				$items[] = $rawItem;
				continue;
			}

			$item = [];

			foreach ($this->fields as $field) {
				$local = (string) $field->name->removePrefix();
				$item[$local] = $rawItem[$local] ?? null;
			}

			// Drop fully-empty items so a blank "add" row never becomes a real item.
			if ($this->isEmptyItem($item)) {
				continue;
			}

			$items[] = $item;
		}

		return new Property\Value($items);
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private function isEmptyItem(array $item): bool
	{
		foreach ($item as $value) {
			// Recurse into composite sub-field values (e.g. a per-item address) so an
			// item whose every leaf is blank is still dropped.
			if (is_array($value)) {
				if (!$this->isEmptyItem($value)) {
					return false;
				}

				continue;
			}

			if ($value !== null && $value !== '') {
				return false;
			}
		}

		return true;
	}
}
