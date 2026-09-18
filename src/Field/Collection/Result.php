<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Collection;

use Meraki\Schema\Field\Collection;
use Meraki\Schema\Message;
use Meraki\Schema\ResolvedField;
use Meraki\Schema\ValidationResult;
use Meraki\Schema\ValueSource;
use Brick\DateTime\Instant;

/**
 * A collection resolved against one request: everything any field's result carries, plus one
 * {@see Item} per submitted row.
 *
 * ### It *is* a resolved field rather than wrapping one
 *
 * It used to hold a private `ResolvedField` and re-expose a chosen few of its members through
 * virtual properties. That made a collection the one field whose result did not look like a
 * result: `given`, `source` and `evaluatedAt` were not reachable at all, and `value` was a `get`
 * hook — so it did not appear in a `var_dump()` either, and a collection result read as though it
 * had no value.
 *
 * Extending is the whole fix. Every member arrives as a real property, so a caller writes the same
 * code for a collection as for a text field and a debugger shows the same thing.
 *
 * ### Two axes, and both count
 *
 * `minCount` is about the list; `starts_at` being in the past is about row 3. The rows are passed
 * to the parent alongside the collection's own verdicts, so `anyFailed()` and `$status` cover
 * both — a collection whose third row failed is a failed field, which is what a form needs to
 * know. Asking about each separately is {@see self::forConstraint()} and {@see self::itemAt()},
 * and neither is flattened into the other.
 */
final class Result extends ResolvedField
{
	/**
	 * @param array<string|int, Item> $items one per submitted row, in the order they arrived and
	 *        under the key they arrived with
	 * @param ValidationResult ...$results the collection's *own* shape and constraint verdicts
	 */
	public function __construct(
		Collection $field,
		mixed $given,
		mixed $value,
		array $appliedOutcomes = [],
		ValueSource $source = ValueSource::Submitted,
		?Instant $evaluatedAt = null,
		public readonly array $items = [],
		ValidationResult ...$results,
	) {
		// array_values because a string key spread into a variadic becomes a *named argument*,
		// which a `ValidationResult ...` parameter cannot accept. The keys are kept on $items,
		// where they are addressable.
		parent::__construct(
			$field,
			$given,
			$value,
			$appliedOutcomes,
			$source,
			$evaluatedAt,
			...$results,
			...array_values($items),
		);
	}

	/**
	 * One row's result, by the key it was submitted under — a position for a plain list, a name
	 * for an array that gave one. `null` if there was no such row.
	 */
	public function itemAt(string|int $key): ?Item
	{
		return $this->items[$key] ?? null;
	}

	/**
	 * Every row that failed, so a caller can report them without walking the list.
	 *
	 * @var list<Item>
	 */
	public array $failedItems {
		get => array_values(array_filter($this->items, static fn(Item $item): bool => $item->anyFailed()));
	}

	/**
	 * Kept as a {@see self} so attaching verdicts does not drop back to a plain
	 * {@see ResolvedField} and lose the rows.
	 */
	public function withResults(ValidationResult ...$results): self
	{
		return new self(
			$this->collection,
			$this->given,
			$this->value,
			$this->appliedOutcomes,
			$this->source,
			$this->evaluatedAt,
			$this->items,
			...$results,
		);
	}

	/**
	 * The collection's own messages, and every row's.
	 *
	 * A collection fails on two axes — the list is too short, *and* the third row's date is in the
	 * past — and they need different sentences in different places. So this translates the
	 * collection's own verdicts through the inherited path and then rebuilds every item so each
	 * field inside a row carries its own messages.
	 *
	 * Rebuilt through the constructor rather than cloned piecemeal because the items are also spread
	 * into `$results`, where they are what makes a failing row fail the collection. Replacing one
	 * copy and not the other would leave a caller reading the same row twice and getting messages
	 * only once.
	 */
	public function withMessagesFrom(?Message\Translator $translator): static
	{
		$items = array_map(
			static fn(Item $item): Item => $item->withMessagesFrom($translator),
			$this->items,
		);

		$own = [];

		foreach ($this->results as $result) {
			if (!$result instanceof Item) {
				$own[] = $result;
			}
		}

		$copy = new self(
			$this->collection,
			$this->given,
			$this->value,
			$this->appliedOutcomes,
			$this->source,
			$this->evaluatedAt,
			$items,
			...$own,
		);

		return clone($copy, ['messages' => Message\Set::for($copy, $translator)]);
	}

	/**
	 * The field, typed as what it always is.
	 *
	 * `$field` is inherited and declared `Field`, and PHP will not let a subclass narrow a
	 * property's type. This is the narrowing, as a separate name rather than a lie about the
	 * inherited one.
	 */
	public Collection $collection {
		get {
			assert($this->field instanceof Collection);

			return $this->field;
		}
	}
}
