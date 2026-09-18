<?php
declare(strict_types=1);

namespace Meraki\Schema\Message;

use Meraki\Schema\Field;

/**
 * Wording in one language, bound and ready.
 *
 * The half of the messaging contract that does the work. A {@see Provider} owns lookup *across*
 * languages; this is what a single language looks like once one has been chosen, and it is what
 * every field result in a request is handed.
 *
 * It answers about verdicts, not about fields. A translator is never asked "what is this field
 * called" — the core has no labels, because a label is a presentation concern and belongs to
 * whatever draws the form. It is asked what to say about a shape that failed and a constraint that
 * failed, and nothing else.
 *
 * ### Returning null is a real answer
 *
 * `null` means "I have no wording for this", and the message is simply absent. It is not an error
 * and it must not become one: a pack that covers ninety of a hundred constraints is a useful pack,
 * and the ten it misses should leave a consumer with no sentence rather than with an exception
 * during rendering. Whether a pack *ought* to cover something is a question for the pack's own
 * build — see {@see Mf2\PackValidator} — where it can be answered before anybody ships it.
 */
interface Translator
{
	/**
	 * The language this is bound to, as the provider resolved it.
	 *
	 * Not necessarily the tag that was asked for: a request for `en-AU` served by a pack with only
	 * `en` reports `en`, so a consumer can tell which wording it actually got.
	 */
	public string $locale { get; }

	/**
	 * What to say when a value could not be read as this field's kind of thing at all, or when
	 * nothing arrived for a field that required something.
	 *
	 * The two are deliberately one method taking a {@see Field\ShapeValidationResult}, because the
	 * result already distinguishes them — `wasMissing()` against `wasUnreadable()` — and splitting
	 * it here would mean adding a method the day a third kind of shape failure appears.
	 */
	public function forShape(Field $field, Field\ShapeValidationResult $shape): ?string;

	/**
	 * What to say when one constraint rejected a value.
	 *
	 * The result carries everything a sentence needs without reaching back to the field: the name
	 * that failed, the part of a structured value it concerns, and the bound that applied —
	 * including a bound that only exists once a value names it, like a postcode pattern for one
	 * country.
	 */
	public function forConstraint(Field $field, Field\ConstraintValidationResult $constraint): ?string;
}
