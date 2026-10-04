<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Exception\InvalidScope;
use Meraki\Schema\Exception\ReadOnlyResult;
use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Everything wrong with one field, in reading order: the value as a whole first, then each part in
 * the order the value declares them.
 *
 * The single place a consumer learns what to tell somebody. It replaced a message set that held
 * sentences only, so a renderer that wanted to style a missing part differently from a wrong one —
 * or to say anything at all without a language pack — had to go back to the constraints and match
 * names. Each entry here carries its code, its part, its bound and its sentence together.
 *
 *     $result->violations->first()?->message;              // the one to show when there is room for one
 *     $result->forPart(Address\Part::PostalCode)[0];       // the first thing wrong with the postcode
 *     foreach ($result->violations as $violation) { … }
 *
 * Read-only: it implements {@see \ArrayAccess} so an entry reads like an array element, and refuses
 * to be written to, because a result is a record of what was decided.
 *
 * @implements IteratorAggregate<int, Violation>
 * @implements ArrayAccess<int, Violation>
 */
final class Violations implements IteratorAggregate, ArrayAccess, Countable
{
	/** @var list<Violation> */
	private readonly array $violations;

	/**
	 * @param list<Part> $declared every part the field's value has, in the order it declares them;
	 *        used to order the entries and to refuse a part the value does not have. Empty for a
	 *        field whose value is one thing.
	 */
	public function __construct(
		private readonly array $declared = [],
		Violation ...$violations,
	) {
		$this->violations = self::inReadingOrder($declared, array_values($violations));
	}

	/**
	 * The one to show when there is only room for one, or null when nothing is wrong.
	 *
	 * A method because it is a question — "which comes first" — rather than a piece of state.
	 */
	public function first(): ?Violation
	{
		return $this->violations[0] ?? null;
	}

	public function isEmpty(): bool
	{
		return $this->violations === [];
	}

	/**
	 * What is wrong with one part, empty when nothing is.
	 *
	 * Refuses a part the value does not have rather than answering emptily, because "nothing is
	 * wrong" is a legitimate answer for a part that is fine — so a mistake that returned it would
	 * be invisible forever. The part is an enum case, so a misspelling does not get that far.
	 *
	 * @throws InvalidScope naming the parts there are
	 */
	public function forPart(Part $part): self
	{
		if (!in_array($part, $this->declared, true)) {
			throw InvalidScope::noSuchPartToReport(
				sprintf('%s::%s', $part::class, $part->name),
				array_map(static fn(Part $declared): string => sprintf('%s::%s', $declared::class, $declared->name), $this->declared),
			);
		}

		return new self($this->declared, ...array_filter(
			$this->violations,
			static fn(Violation $violation): bool => $violation->part === $part,
		));
	}

	/**
	 * What is wrong with the value as a whole rather than with one of its parts — a field with
	 * nothing submitted, a value that could not be read at all, a minimum length.
	 */
	public function forWholeValue(): self
	{
		return new self($this->declared, ...array_filter(
			$this->violations,
			static fn(Violation $violation): bool => $violation->part === null,
		));
	}

	/**
	 * The parts that have something wrong with them, in the order the value declares them.
	 *
	 * Only those: a form drawing an error summary wants the two parts that failed, not all six.
	 *
	 * @var list<Part>
	 */
	public array $parts {
		get => array_values(array_filter(
			$this->declared,
			fn(Part $part): bool => array_any($this->violations, static fn(Violation $v): bool => $v->part === $part),
		));
	}

	/**
	 * Every sentence a language pack had, in reading order. A violation no pack had wording for is
	 * left out, so this is empty whenever no pack was asked.
	 *
	 * @var list<string>
	 */
	public array $messages {
		get => array_values(array_filter(
			array_map(static fn(Violation $violation): ?string => $violation->message, $this->violations),
			static fn(?string $message): bool => $message !== null,
		));
	}

	/**
	 * The same violations, each worded by the callback.
	 *
	 * Plumbing for a result rendering its messages, kept here so the order and the declared parts
	 * travel with the copy.
	 *
	 * @param callable(Violation): ?string $word
	 */
	public function worded(callable $word): self
	{
		return new self($this->declared, ...array_map(
			static fn(Violation $violation): Violation => $violation->withMessage($word($violation)),
			$this->violations,
		));
	}

	/** @return Traversable<int, Violation> */
	public function getIterator(): Traversable
	{
		return new ArrayIterator($this->violations);
	}

	public function count(): int
	{
		return count($this->violations);
	}

	/** @param int $offset */
	public function offsetExists(mixed $offset): bool
	{
		return isset($this->violations[$offset]);
	}

	/**
	 * An entry by position, or null past the end — the same answer `first()` gives an empty set.
	 *
	 * @param int $offset
	 */
	public function offsetGet(mixed $offset): ?Violation
	{
		return $this->violations[$offset] ?? null;
	}

	/** @throws ReadOnlyResult always: a result is a record of what was decided */
	public function offsetSet(mixed $offset, mixed $value): never
	{
		throw ReadOnlyResult::cannotBeWrittenTo(self::class);
	}

	/** @throws ReadOnlyResult always: a result is a record of what was decided */
	public function offsetUnset(mixed $offset): never
	{
		throw ReadOnlyResult::cannotBeWrittenTo(self::class);
	}

	/**
	 * The value as a whole first, then each part in declared order; within each, as reported.
	 *
	 * Declared order rather than the order checks happened to run in, so two requests failing the
	 * same way always read the same way.
	 *
	 * @param list<Part> $declared
	 * @param list<Violation> $violations
	 * @return list<Violation>
	 */
	private static function inReadingOrder(array $declared, array $violations): array
	{
		$rank = static function (Violation $violation) use ($declared): int {
			if ($violation->part === null) {
				return -1;
			}

			$at = array_search($violation->part, $declared, true);

			return $at === false ? count($declared) : $at;
		};

		// usort is stable since PHP 8.0, so entries of equal rank keep the order they were reported.
		usort($violations, static fn(Violation $a, Violation $b): int => $rank($a) <=> $rank($b));

		return $violations;
	}
}
