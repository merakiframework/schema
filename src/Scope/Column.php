<?php
declare(strict_types=1);

namespace Meraki\Schema\Scope;

use Meraki\Schema\FieldName;
use Meraki\Schema\Scope;
use Meraki\Schema\ValueScope;

/**
 * One template field across every row — `#/fields/attendees/value/*​/email`.
 *
 * The question a collection is usually asked: not "what did row `alice` say" but "what did any of
 * them say". It resolves to a list, under the row names, which is what
 * {@see \Meraki\Schema\Field\Collection\Value::column()} already returned before anything could
 * address it.
 *
 * ### Why `*` and not a name
 *
 * It sits exactly where a row name sits, and cannot be mistaken for one because a name may not
 * contain `*` — the same rule that removes the need to escape anything. Putting the wildcard in the
 * row slot rather than inventing a fifth marker segment is what keeps every collection reading
 * `…/value/<which>/<field>`, with `*` simply meaning "all of them".
 */
final readonly class Column implements Locator
{
	/** The row slot's wildcard. Not a legal name, so it can never collide with a row. */
	public const EVERY_ROW = '*';

	public function __construct(
		public FieldName $field,
		private FieldName $templateField,
	) {
	}

	public function addresses(): FieldName
	{
		return $this->templateField;
	}

	public function __toString(): string
	{
		return '#/' . Scope::FIELD_COLLECTION . '/' . $this->field
			. '/' . ValueScope::SEGMENT
			. '/' . self::EVERY_ROW
			. '/' . $this->templateField;
	}
}
