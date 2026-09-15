<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Collection;

use Meraki\Schema\Field\Equality;
use Meraki\Schema\Field\ParsedValue;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * The rows of a collection, held as one value.
 *
 * The last field to get one, and the one whose case was weakest: a collection holds *many* values
 * rather than one, so it was the obvious candidate for an exemption. It does not get one, for the
 * same reason scalars do not — an exemption anywhere means every consumer that compares, renders or
 * serialises a value has to ask what kind of thing it is holding first. With this,
 * {@see \Meraki\Schema\Field\Definition::parse()} returns a {@see ParsedValue} and nothing else.
 *
 * ### Rows keep the keys they arrived with
 *
 * `['line item 1' => …]` names its rows and the names survive to here, so a failure can be reported
 * against something a person recognises. A plain list keys itself `0, 1, 2`. Mixed keys never reach
 * this object — {@see \Meraki\Schema\Field\Collection} refuses them, because PHP numbers whatever
 * was not named and which row `0` refers to would then depend on how many names came before it.
 *
 * ### A row is a record, not a value object
 *
 * Each row is a `stdClass` with one property per template field, and each of *those* is an ordinary
 * parsed value. So the rule holds where it matters — at the leaves — and a row stays a plain record
 * of them rather than growing a class per collection.
 */
final readonly class Value implements ParsedValue, IteratorAggregate, Countable
{
	/**
	 * @param array<string|int, object|mixed> $rows one per submitted item, keyed as submitted.
	 *        A row that was not a record at all is kept exactly as it came, so it fails on its own
	 *        terms instead of being quietly reshaped into something it is not.
	 */
	public function __construct(public array $rows)
	{
	}

	/**
	 * The same rows, in the same order, under the same keys.
	 *
	 * Order counts. Two invoices with the same lines in a different order are not obviously the
	 * same invoice, and deciding they were would be this object inventing a rule the author never
	 * asked for. An author who wants order-insensitivity has a set, and can say so by naming rows.
	 */
	public function equals(ParsedValue $other): bool
	{
		if (!$other instanceof self || array_keys($this->rows) !== array_keys($other->rows)) {
			return false;
		}

		foreach ($this->rows as $key => $row) {
			if (!self::sameRow($row, $other->rows[$key])) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether any row appears more than once.
	 *
	 * Lives here rather than on the field because it is a question about the list, and the list is
	 * this. The field decides only whether repeats are *allowed* — see
	 * {@see \Meraki\Schema\Field\Collection::allowDuplicates()}.
	 *
	 * Compared on the rows as the template resolved them, so each field is the authority on what
	 * was actually entered. Where a field canonicalises, the canonicalisation counts: two rows
	 * holding one UUID in different cases are one row, because `Uuid\Value` says so, and
	 * `alice@example.test` and `alice@EXAMPLE.test` are one guest, because DNS says two spellings
	 * are one host.
	 */
	public function hasRepeats(): bool
	{
		$seen = [];

		foreach ($this->rows as $row) {
			foreach ($seen as $earlier) {
				if (self::sameRow($earlier, $row)) {
					return true;
				}
			}

			$seen[] = $row;
		}

		return false;
	}

	public function count(): int
	{
		return count($this->rows);
	}

	public function isEmpty(): bool
	{
		return $this->rows === [];
	}

	/**
	 * Iterates the rows under their own keys, so a named row stays named.
	 *
	 * @return Traversable<string|int, object|mixed>
	 */
	public function getIterator(): Traversable
	{
		yield from $this->rows;
	}

	/**
	 * Two rows, compared field by field.
	 *
	 * `get_object_vars()` from out here reads *public* properties only, which is exactly what a row
	 * is — one value per template field, with nothing private to mistake for one.
	 */
	private static function sameRow(mixed $a, mixed $b): bool
	{
		if (!$a instanceof \stdClass || !$b instanceof \stdClass) {
			// One of them was not a record. Such a row is kept verbatim so it can fail on its own
			// terms, and comparing it is the most this can honestly do.
			return Equality::same($a, $b);
		}

		$left = get_object_vars($a);
		$right = get_object_vars($b);

		if (array_keys($left) !== array_keys($right)) {
			return false;
		}

		foreach ($left as $field => $value) {
			if (!Equality::same($value, $right[$field])) {
				return false;
			}
		}

		return true;
	}
}
