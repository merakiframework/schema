<?php
declare(strict_types=1);

namespace Meraki\Schema\Scope;

use Meraki\Schema\Exception\InvalidScope;
use Meraki\Schema\FieldName;
use Meraki\Schema\Scope;
use Meraki\Schema\ValueScope;

/**
 * One named row of a collection — `#/fields/attendees/value/alice/email`.
 *
 * ### Why a name, and never a position
 *
 * A position meant a different row the moment anything was inserted above it, so a *stored* rule
 * naming one silently changed its mind between requests. That is why a collection's rows were
 * unaddressable at all before now, and it is why {@see \Meraki\Schema\Field\Collection} refuses a
 * positional list outright: `alice` means the same row on every request, and `1` never could.
 *
 * Because a row key is held to the same pattern as a field name, this needs no escaping — a name
 * cannot contain the `/` that separates segments, and `*` cannot be a name, which is what leaves it
 * free to mean {@see Column}.
 *
 * ### The row is not checked here
 *
 * Whether that row was submitted is a fact about a request, and a scope is written long before one
 * arrives. So the *shape* is checked — it has to be a name — and existence is not: an absent row
 * resolves to nothing, exactly as an unfilled part of an address does.
 */
final readonly class Row implements Locator
{
	/**
	 * @throws InvalidScope if the row is not shaped like a name
	 */
	public function __construct(
		public FieldName $field,
		public string $row,
		private FieldName $templateField,
	) {
		if (!FieldName::isUsable($this->row)) {
			throw InvalidScope::rowIsNotAName((string) $this->field, $this->row);
		}
	}

	public function addresses(): FieldName
	{
		return $this->templateField;
	}

	public function __toString(): string
	{
		return '#/' . Scope::FIELD_COLLECTION . '/' . $this->field
			. '/' . ValueScope::SEGMENT
			. '/' . $this->row
			. '/' . $this->templateField;
	}
}
