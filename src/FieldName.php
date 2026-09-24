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

	public function equals(self $other): bool
	{
		return strcasecmp($this->value, $other->value) === 0;
	}

	public function __toString(): string
	{
		return $this->value;
	}
}
