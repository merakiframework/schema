<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use InvalidArgumentException;
use Meraki\Schema\Facade;
use Meraki\Schema\Scope;

/**
 * Holds when the value's text contains the given text.
 *
 *     $notes->when()->contains('urgent')->then($escalationContact->makeRequired());
 *
 * A plain substring test, case-sensitive. Case-insensitivity is {@see Matches} with an `i` flag,
 * rather than a second matcher or an option nobody would find.
 *
 * ### Text only, and deliberately not collections
 *
 * The original vocabulary had this covering collections too — "does this list contain X". That is
 * dropped, because a collection's rows are *records* and no honest reading of
 * `contains('SKU-1')` exists over them: the needle would have to name a field as well as a value,
 * which is a different matcher with a different signature. Folding both behind one verb would mean
 * `contains` meaning membership or substring depending on what the request happened to submit,
 * decided at runtime, silently.
 *
 * What that needs is a scope that can address a column — `#/fields/lines/value/sku` across every
 * row — at which point `isIn` already says it. That is on the roadmap; this is not a stand-in for
 * it.
 *
 * See {@see Textual} for which values have text at all, and why a password does not.
 */
final class Contains extends Textual
{
	/**
	 * @throws InvalidArgumentException if the needle is empty
	 */
	public function __construct(Scope|string $target, public readonly string $needle)
	{
		parent::__construct($target);

		// Every string contains the empty string, so this would hold for every request that
		// submitted anything — a rule that looks conditional and is not.
		if ($needle === '') {
			throw new InvalidArgumentException(
				'contains() was given an empty string, which every value contains. '
				. 'Use isNotEmpty() if that is what you meant.',
			);
		}
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function matches(array $data, Facade $schema): bool
	{
		$text = $this->textAt($data, $schema);

		return $text !== null && str_contains($text, $this->needle);
	}
}
