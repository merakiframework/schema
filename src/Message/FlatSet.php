<?php
declare(strict_types=1);

namespace Meraki\Schema\Message;

/**
 * What to say about a field that holds one value.
 *
 * A list of sentences, in the order they were reached: the shape first if the value could not be
 * read at all, then one per failed constraint in the order the field declares them. That ordering
 * is why `$first` is worth having — the earliest failure is the most useful thing to show, because
 * everything after it was judged against a value the user has yet to fix.
 *
 * Also what {@see PartedSet} hands back for one part, so a caller that drilled into a part is
 * holding the same kind of thing it would have held for a simple field.
 */
final class FlatSet extends Set
{
	/** @var list<string> */
	public readonly array $all;

	public function __construct(string ...$messages)
	{
		$this->all = array_values($messages);
	}
}
