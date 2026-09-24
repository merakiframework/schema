<?php
declare(strict_types=1);

namespace Meraki\Schema\Scope;

use Meraki\Schema\FieldName;
use Meraki\Schema\Scope;

/**
 * A template field's own definition — `#/fields/attendees/template/email`.
 *
 * Row-agnostic, and the only one of the four that is pure definition: `…/template/email/minLength`
 * is the same answer for every request, because it is a fact about the template rather than about
 * what anybody submitted.
 *
 * `template` is already a public property of {@see \Meraki\Schema\Field\Collection}, so
 * `#/fields/attendees/template` resolves as an ordinary property scope and hands back the whole
 * list. This reads as a deepening of that rather than as a new idea, which is why the marker is
 * borrowed from the property instead of invented — and why a three-segment path keeps its old
 * meaning untouched.
 *
 * ### `…/template/<tf>/value` needs a row
 *
 * The definition is row-agnostic; a value is not. Inside a rule applied per row it binds to the row
 * being validated, which is how a rule says "this row's email". Outside one there is no row to bind
 * to, and guessing between "the first" and "all of them" would be a silent answer to a question
 * nobody asked — {@see Column} is how you ask about every row.
 */
final readonly class Template implements Locator
{
	/** The marker segment, which doubles as the name of the property it deepens. */
	public const SEGMENT = 'template';

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
			. '/' . self::SEGMENT
			. '/' . $this->templateField;
	}
}
