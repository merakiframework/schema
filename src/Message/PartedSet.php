<?php
declare(strict_types=1);

namespace Meraki\Schema\Message;

use Meraki\Schema\Exception\InvalidScope;

/**
 * What to say about a field whose value is made of named parts.
 *
 * An address that failed on its postcode and its state has two problems in two places, and a
 * renderer wants to put each sentence beside the input it belongs to. Flattening them would throw
 * that away, and the only way back would be to parse the sentences.
 *
 * The grouping is the same vocabulary used everywhere else: the part names a value declares
 * through {@see \Meraki\Schema\Field\HasParts::partNames()}, which are also the keys input arrives
 * under and the values a constraint reports as its `part`. A consumer that has seen either already
 * knows these names.
 *
 *     $messages = $result->forField('billing')->messages;
 *
 *     foreach ($messages->whole as $said) { ... }             // wrong with the address itself
 *     foreach ($messages->parts as $part) {
 *         foreach ($messages->forPart($part) as $said) { ... }
 *     }
 *
 * `$all` still works and still reads correctly, so a consumer that does not care about parts is
 * not made to.
 */
final class PartedSet extends Set
{
	/**
	 * @param FlatSet $whole what is wrong with the value considered as a whole — an unreadable
	 *        card, an address whose country is not allowed. Constraints report a null part for
	 *        exactly this.
	 * @param array<string, FlatSet> $byPart what is wrong with each part, keyed by part name.
	 *        Parts with nothing wrong are absent rather than present and empty; {@see self::$parts}
	 *        is how you find out which those are.
	 * @param list<string> $partNames every part the value has, in the order it declares them,
	 *        whether or not anything is wrong with it. Used to order the output and to refuse a
	 *        part that does not exist.
	 */
	public function __construct(
		public readonly FlatSet $whole,
		private readonly array $byPart,
		private readonly array $partNames,
	) {
	}

	/**
	 * Every sentence: the whole value's first, then each part's in the order the value declares
	 * its parts.
	 *
	 * Declared order rather than failure order, so two requests failing the same way always read
	 * the same way — the order constraints happened to run in is not something a user should be
	 * able to notice.
	 *
	 * @var list<string>
	 */
	public array $all {
		get {
			$all = $this->whole->all;

			foreach ($this->partNames as $part) {
				if (isset($this->byPart[$part])) {
					$all = [...$all, ...$this->byPart[$part]->all];
				}
			}

			return $all;
		}
	}

	/**
	 * The parts that have something wrong with them, in declared order.
	 *
	 * Only those: a form drawing an error summary wants the four parts that failed, not all seven
	 * with three of them empty. Ask {@see self::forPart()} about any part you already know the
	 * name of.
	 *
	 * @var list<string>
	 */
	public array $parts {
		get => array_values(array_filter(
			$this->partNames,
			fn(string $part): bool => isset($this->byPart[$part]),
		));
	}

	/**
	 * What is wrong with one part, empty when nothing is.
	 *
	 * Refuses a part the value does not have rather than answering emptily, for the same reason
	 * {@see \Meraki\Schema\ScopeResolver} refuses a mistyped part in a scope: "no messages" is a
	 * legitimate answer for a part that is fine, so a typo that returned it would be invisible
	 * forever.
	 *
	 * @throws InvalidScope naming the parts there are
	 */
	public function forPart(string $part): FlatSet
	{
		if (!in_array($part, $this->partNames, true)) {
			throw InvalidScope::noSuchPartToReport($part, $this->partNames);
		}

		return $this->byPart[$part] ?? new FlatSet();
	}
}
