<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use InvalidArgumentException;
use Meraki\Schema\Facade;
use Meraki\Schema\Scope;

/**
 * Holds when the value's text matches the given pattern.
 *
 *     $schema->when($reference)->matches('/^INV-/')->thenRequire($invoiceDate);
 *
 * A PCRE pattern, delimiters and all, which is the same thing `Text::matching()` takes — one
 * spelling of "a pattern" across the field surface and the rule surface.
 *
 * ### The pattern is checked where the rule is written
 *
 * A pattern that does not compile makes `preg_match()` return `false` and emit a warning, and a
 * condition reading that as "did not match" is a rule that quietly never fires. So it is compiled
 * once, in the constructor, against the empty string — which exercises the parser without
 * depending on any input — and a bad one raises next to the line that wrote it.
 *
 * This is the half of the authoring check that *is* available here. Whether the field has text at
 * all is not — see {@see Textual}.
 *
 * ### It cannot read a password or a card number
 *
 * Not an oversight, and worth knowing before you reach for it: those two values have no string
 * form, so this never holds for them. See {@see Textual}.
 */
final class Matches extends Textual
{
	/**
	 * @throws InvalidArgumentException if the pattern is not a valid PCRE
	 */
	public function __construct(Scope|string $target, public readonly string $pattern)
	{
		parent::__construct($target);

		// @ rather than a warning handler: a malformed pattern is reported by the return value,
		// and the warning would otherwise escape as output in the middle of building a schema.
		if (@preg_match($pattern, '') === false) {
			throw new InvalidArgumentException(sprintf(
				'matches() was given %s, which is not a valid pattern. It takes a PCRE with its '
				. 'delimiters, the same as Text::matching() — "/^INV-/" rather than "^INV-".',
				var_export($pattern, true),
			));
		}
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function matches(array $data, Facade $schema): bool
	{
		$text = $this->textAt($data, $schema);

		return $text !== null && preg_match($this->pattern, $text) === 1;
	}
}
