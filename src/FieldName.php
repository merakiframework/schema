<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Exception\InvalidFieldName;
use Stringable;

final readonly class FieldName implements Stringable
{
	private const PATTERN = '/^[A-Za-z_][A-Za-z0-9_-]*$/';

	public function __construct(
		private string $value
	) {
		if ($this->value === '') {
			throw InvalidFieldName::isEmpty();
		}

		if (!self::isUsable($this->value)) {
			throw InvalidFieldName::isNotUsable($this->value);
		}
	}

	/**
	 * Whether a string is shaped like a name, without raising if it is not.
	 *
	 * A name is snake, camel, pascal or slug case: letters, digits, `_` and `-`, never starting
	 * with a digit. The constructor asks this and throws; a *row key* asks it and fails the
	 * request instead, because a key arrives from a submitter rather than from an author —
	 * see {@see Field\Collection::rowsIn()}.
	 *
	 * Here rather than duplicated there, so there is one answer to "is this a name" and it cannot
	 * drift. The digit rule is what makes a row key unambiguous: PHP turns the array key `'0'`
	 * into `0`, so a name that could begin with a digit would be indistinguishable from a
	 * position.
	 */
	public static function isUsable(string $value): bool
	{
		return preg_match(self::PATTERN, $value) === 1;
	}

	/**
	 * Whether this is the same name, spelled the same way.
	 *
	 * **Exact**, because a name is a wire key. It is what a payload is keyed by, what a scope
	 * path spells, and what `forField()` is given — and every one of those lookups is an exact
	 * string comparison. This used to fold case, which made it the only part of the system that
	 * did, and the disagreement was silent in both directions: a collection template holding
	 * `Name` and `name` built without complaint and then threw `DuplicateFieldName` on every
	 * request, and `thenIgnore('Detail')` against a field called `detail` passed every authoring
	 * check and then never applied.
	 *
	 * What folding case was *for* has not gone away — see {@see self::collidesWith()}.
	 */
	public function equals(self $other): bool
	{
		return $this->value === $other->value;
	}

	/**
	 * Whether two names are too alike to live on one schema.
	 *
	 * The job {@see self::equals()} used to do by folding case, kept and given its own name. A
	 * schema holding both `email` and `Email` leaves a reader guessing which one a message or a
	 * scope path meant, so it is refused where the fields are added — at authoring time, by
	 * {@see Field\Set} and {@see Field\Collection}, rather than by making the two names *equal*
	 * and leaving every exact-match lookup in the library to disagree with that.
	 *
	 * So the two questions are separate now because they always were: "is this the same field"
	 * is asked on every request, and "could these be confused" is asked once, when the schema is
	 * written.
	 */
	public function collidesWith(self $other): bool
	{
		return strcasecmp($this->value, $other->value) === 0;
	}

	public function __toString(): string
	{
		return $this->value;
	}
}
