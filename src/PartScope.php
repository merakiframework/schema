<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Exception\InvalidScope;

/**
 * Names one part of what a field was given — `#/fields/billing_address/value/country`.
 *
 * {@see ValueScope} is the whole value; this is a piece of it. Both are questions about the
 * request rather than about the definition, which is why this sits under `value` rather than
 * beside it.
 *
 * ### Why four segments rather than three
 *
 * `#/fields/billing_address/country` reads better and is ambiguous. The third segment already
 * means *a property of the definition* — `#/fields/age/minValue` — and two fields have a
 * definition property whose name collides with a part of their value:
 *
 * | Scope | Could mean |
 * | --- | --- |
 * | `#/fields/card/name` | the field's own name, or the cardholder's |
 * | `#/fields/resume/name` | the field's own name, or the uploaded file's |
 *
 * Neither collision is exotic — `$name` is on every field — and a precedence rule would make the
 * meaning of a *stored* scope depend on which properties a field happens to have, so adding one
 * later could silently change what an existing rule asks. Putting parts under `value` costs a
 * segment and removes the question.
 *
 * It also says something true: a part is part of the *value*, and the value is what `value` names.
 */
final readonly class PartScope extends Scope
{
	/** The part's wire name — `postal_code` — which is what a stored scope holds. */
	public string $part;

	/**
	 * @param Field\Part|string $part the part's case — `Address\Part::PostalCode` — or its wire
	 *        name, which is what a serialised scope holds. In code, pass the case: a misspelled
	 *        case does not compile, and a misspelled name is only refused where the rule is added.
	 */
	public function __construct(FieldName|Scope\Locator $in, Field\Part|string $part)
	{
		$this->part = $part instanceof Field\Part ? (string) $part->value : $part;

		if ($this->part === '') {
			throw InvalidScope::partIsMissing();
		}

		parent::__construct($in);
	}

	/**
	 *     PartScope::of('billing', Address\Part::Country)   // #/fields/billing/value/country
	 */
	public static function of(FieldName|string $field, Field\Part|string $part): self
	{
		return new self($field instanceof FieldName ? $field : new FieldName($field), $part);
	}

	public function __toString(): string
	{
		return $this->prefix() . '/' . ValueScope::SEGMENT . '/' . $this->part;
	}
}
