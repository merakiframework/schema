<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Collection;

use Meraki\Schema\Field\Collection;
use Meraki\Schema\Field\ConstraintValidationResult;
use Meraki\Schema\Field\ShapeValidationResult;
use Meraki\Schema\AggregatedValidationResult;
use Meraki\Schema\Field;
use Meraki\Schema\FieldResult;
use Meraki\Schema\ResolvedField;
use Meraki\Schema\ValidationStatus;

/**
 * A collection resolved against one request: the collection's own verdicts, plus one
 * {@see Item} per submitted item.
 *
 * Both halves are needed and they answer different questions. `minCount` is about the list;
 * `starts_at` being in the past is about item 3. Aggregating them means `anyFailed()` covers
 * everything, while `get()` and `item()` let a caller ask about each separately.
 *
 * @extends AggregatedValidationResult<ResolvedField|Item>
 */
final class Result extends AggregatedValidationResult implements FieldResult
{
	/**
	 * @param ResolvedField $own the collection's own value and constraint verdicts
	 * @param array<string|int, Item> $items one per submitted item, in the order they arrived and
	 *        under the key they arrived with
	 */
	public function __construct(
		public readonly Collection $field,
		private readonly ResolvedField $own,
		public readonly array $items = [],
	) {
		// array_values because a string key spread into a variadic becomes a *named argument*,
		// which `ValidationResult ...$results` cannot accept. The aggregate only needs the
		// verdicts; the keys are kept on $items, where they are addressable.
		parent::__construct($own, ...array_values($items));
	}

	/**
	 * Whether the submitted value was a list at all — the collection's own shape, not any item's.
	 *
	 * Read off the inner result rather than recomputed, so there is one answer to the question.
	 */
	public ShapeValidationResult $shape {
		get => $this->own->shape;
	}

	/**
	 * The collection's *own* constraint names — `minCount`, `maxCount`, `unique` — not any item's,
	 * for the same reason {@see self::get()} reads only its own.
	 *
	 * @return list<string>
	 */
	public array $constraintNames {
		get => $this->own->constraintNames;
	}

	/**
	 * One of the collection's *own* constraints, by name — `minCount`, `maxCount`.
	 *
	 * Not an item's: ask for that with {@see self::item()}, because `minCount` and an item's
	 * `starts_at` are different kinds of answer and flattening them is what made failures
	 * unattributable before.
	 */
	public function forConstraint(string $constraintName): ?ConstraintValidationResult
	{
		return $this->own->forConstraint($constraintName);
	}

	/**
	 * One item's result, by the key it was submitted under — a position for a plain list, a name
	 * for an array that gave one. `null` if there was no such item.
	 */
	public function itemAt(string|int $key): ?Item
	{
		return $this->items[$key] ?? null;
	}

	/**
	 * Every item that failed, so a caller can report them without walking the list.
	 *
	 * @var list<Item>
	 */
	public array $failedItems {
		get => array_values(array_filter($this->items, static fn(Item $item): bool => $item->anyFailed()));
	}

	/**
	 * The list as the collection resolved it — see {@see ResolvedField::$value}.
	 */
	public mixed $value {
		get => $this->own->value;
	}
}
