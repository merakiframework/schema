<?php
declare(strict_types=1);

namespace Meraki\Schema\Message;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Meraki\Schema\Field;
use Meraki\Schema\FieldResult;
use Traversable;

/**
 * What to tell somebody about one field, in the language the request asked for.
 *
 * The single integration point. Everything the messaging layer produces arrives as
 * `$result->forField('billing')->messages`, and it is always a `Set` — empty when no provider was
 * registered, empty when the language was not found, empty when nothing failed. Never null, so
 * reading it never needs a guard.
 *
 * ### Two shapes, and the field decides which
 *
 * A text field has one thing to say about; an address has seven. So:
 *
 * - {@see FlatSet} for a field holding one value — a list of sentences.
 * - {@see PartedSet} for a field whose value is made of named parts, grouping each sentence under
 *   the part it concerns so a renderer can attach it to the right input instead of piling
 *   everything above the fieldset.
 *
 * Which one you get follows from the *field*, via {@see Field\ValueClass::hasParts()}, and not
 * from what happened to fail. That matters: deciding it from the results would mean an address
 * whose only failure was on the whole value came back flat, and a consumer that checks the type
 * once would break on a request that happened to fail differently.
 *
 * ### Assembly lives here, not in the provider
 *
 * A {@see Translator} returns sentences. This turns them into the right shape. Putting it the
 * other way round would make every provider re-implement the grouping, and the first one to get it
 * wrong would be indistinguishable from one that simply had less to say.
 */
abstract class Set implements IteratorAggregate, Countable
{
	/**
	 * Everything there is to say about a resolved field, asked of one language.
	 *
	 * Only *failures* produce sentences. A constraint that passed has nothing to report, and one
	 * that was skipped never ran. A shape failure and a constraint failure are both included, in
	 * that order, because "this is not a valid card number" comes before anything the number would
	 * have been checked against.
	 *
	 * @param Translator|null $translator null for a schema with no provider, which yields an empty
	 *        set of the shape the field would have used anyway.
	 */
	public static function for(FieldResult $result, ?Translator $translator = null): self
	{
		$field = $result->field;
		$parted = Field\ValueClass::hasParts($field);

		if ($translator === null) {
			return $parted ? new PartedSet(new FlatSet(), [], Field\ValueClass::partNamesOf($field)) : new FlatSet();
		}

		$whole = [];
		$byPart = [];

		if ($result->shape->failed()) {
			$said = $translator->forShape($field, $result->shape);

			if ($said !== null) {
				$whole[] = $said;
			}
		}

		foreach ($result->constraints->getFailed() as $constraint) {
			$said = $translator->forConstraint($field, $constraint);

			if ($said === null) {
				continue;
			}

			if ($constraint->part === null) {
				$whole[] = $said;
			} else {
				$byPart[$constraint->part][] = $said;
			}
		}

		if (!$parted) {
			// A field with no parts should never have produced a parted constraint, but a
			// field somebody else wrote might. Folding them in beats dropping them silently.
			foreach ($byPart as $said) {
				$whole = [...$whole, ...$said];
			}

			return new FlatSet(...$whole);
		}

		return new PartedSet(
			new FlatSet(...$whole),
			array_map(static fn(array $said): FlatSet => new FlatSet(...$said), $byPart),
			Field\ValueClass::partNamesOf($field),
		);
	}

	/**
	 * Every sentence, in reading order: what is wrong with the value as a whole, then what is
	 * wrong with each part.
	 *
	 * @var list<string>
	 */
	abstract public array $all { get; }

	/**
	 * The one to show when there is only room for one.
	 *
	 * Forms usually have space for a single error per field, and picking it should not require
	 * knowing that `$all[0]` is the right index or that the list might be empty.
	 */
	public ?string $first {
		get => $this->all[0] ?? null;
	}

	public function isEmpty(): bool
	{
		return $this->all === [];
	}

	/** @return Traversable<int, string> */
	public function getIterator(): Traversable
	{
		return new ArrayIterator($this->all);
	}

	public function count(): int
	{
		return count($this->all);
	}
}
