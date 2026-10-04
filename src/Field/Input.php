<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

/**
 * A record read part by part: what a structured field makes of submitted input before anyone
 * knows whether it is a value.
 *
 * It is the middle of the four steps in docs/DESIGN.md:
 *
 *     record ──► input ──► assembly ──► value ──► constraints
 *
 * Each part is read on its own. It is canonicalised where a standard says two spellings are one
 * thing, and it is `null` where it was absent or could not be read. Then the input says whether
 * those parts make a value: every essential part present, every part readable, and the parts
 * agreeing with each other. If they do, the input holds the value they make.
 *
 * ### Why there is an input at all
 *
 * So that a value is never half of one. Before this, a value with a part missing was still a
 * value: `Money\Value::$amount` was a `?BigDecimal`, and every constraint began by asking which
 * half was there. Now a value's essential parts cannot be null, and a half-filled form has a type
 * of its own, which is this one.
 *
 * ### A rule about one part reads this
 *
 * `#/fields/billing_address/value` is the whole address; `#/fields/billing_address/value/country`
 * is one part of it, and the input is what answers the second. "When the billing country is AU" is
 * a question a half-filled address can answer, so a part scope reads the input rather than the
 * value. See {@see \Meraki\Schema\ScopeResolver}.
 *
 *     // is the whole shipping address the billing address?
 *     $schema->when($shipping)->equals(ValueScope::of('billing'))
 *
 *     // are they at least in the same country?
 *     $schema->when(ValueScope::of('shipping', Address\Part::Country))
 *         ->equals(ValueScope::of('billing', Address\Part::Country))
 *
 * Which parts there are is a fact about the field, not about any request: the cases of its
 * {@see Part} enum, read through {@see \Meraki\Schema\Field::$parts}. A scope asking for a part the
 * field does not declare is refused where the rule is written, rather than resolving to `null` on
 * every request afterwards.
 *
 * This used to be two interfaces, `Input` and a `HasParts` it extended. Nothing implemented the
 * second without the first once every record-shaped value had moved its parts here, so it was one
 * contract with two names.
 *
 * ### What an implementation promises
 *
 * - **`$value` is set exactly when `$violations` is empty.** The lifecycle refuses an input that
 *   reports nothing wrong yet makes nothing, by raising
 *   {@see \Meraki\Schema\Exception\InconsistentInput}. An "incomplete" verdict with no violation
 *   behind it would be a failure nobody could explain.
 * - **Nothing here depends on the field's configuration, and nothing asks what time it is.** That
 *   is the line between assembly and constraints, drawn in docs/DESIGN.md. It is what lets one
 *   input be judged the same way for a default where the schema is written, for a trusted
 *   prefill, and on every request.
 * - **It is immutable, like a value.** Work everything out where it is built.
 *
 * A field reads an input by returning one from {@see Definition::parse()}. A field whose value is
 * one thing returns the value itself and never needs one.
 */
interface Input
{
	/**
	 * Everything that stops these parts making a value, each against the part it is about. Any
	 * order will do, because the result puts them in reading order. Empty when the parts make a
	 * value.
	 *
	 * @var list<Violation>
	 */
	public array $violations { get; }

	/**
	 * The essential parts that were not supplied, in the order the value declares them.
	 *
	 * A part counts as not supplied when it is absent or `null`. A part that was supplied and
	 * could not be read is not missing: it is wrong, and has a violation of its own. Every part
	 * listed here has one too, the `…Required` violation that names it.
	 *
	 * @var list<Part>
	 */
	public array $missingParts { get; }

	/**
	 * The value these parts make, or `null` while anything in {@see self::$violations} stands in
	 * its way.
	 *
	 * An implementation narrows this to its own value class, `public ?Money\Value $value`. That is
	 * how {@see ValueClass} learns what a field holds without needing a request.
	 */
	public ?ParsedValue $value { get; }

	/**
	 * Each part as read, keyed by its {@see Part}'s value — `postal_code`. A part nobody supplied,
	 * or one that could not be read, is present and `null` rather than missing, so reading one is
	 * never a question about whether the key exists.
	 *
	 * ### The names are the ones already in use
	 *
	 * A part is named as it arrives and as it is reported: `postal_code`, not `postalCode`. Those
	 * are the keys {@see Definition::recordIn()} reads from submitted input and the values a
	 * constraint carries as {@see Constraint::$part}, so a consumer that has seen either already
	 * knows this vocabulary.
	 *
	 * ### A reading of a value is not a part of it
	 *
	 * Only what a submitter could fill in on its own belongs here. Something derived from the
	 * parts is a method on the value — `PhoneNumber\Value::toE164()` — and a value submitted as
	 * one thing has no input and no parts: an email address is one box on a form, so `local_part`
	 * and `domain` are a reading of it, not two things to render.
	 *
	 * @return array<string, mixed>
	 */
	public function parts(): array;

	/**
	 * The value this part would hold, had the given spelling been submitted.
	 *
	 * An input canonicalises what it is given, and a rule has to compare against what was
	 * *stored*: an address holds `AU-QLD` whichever of `QLD`, `qld`, `AU-QLD` or `Queensland`
	 * arrived, and `AU` for `Australia`. Without this, `equals('QLD')` compared the stored
	 * `AU-QLD` against `QLD`, was false for every request there would ever be, and said nothing
	 * — a rule written in the very spelling the field accepts as input.
	 *
	 * Asked of the input rather than worked out by the comparison, because only it knows what it
	 * did with what it was given. A subdivision needs its country to resolve, and the submitted
	 * parts are the only thing holding one.
	 *
	 * Return the expectation unchanged for a part that is stored as it arrives, which is most of
	 * them, and wherever it cannot be read the way the part was: a comparison then says it does
	 * not hold, rather than this raising.
	 *
	 * @param Part $part one of the field's {@see \Meraki\Schema\Field::$parts}
	 * @param mixed $expected whatever the rule was written with
	 */
	public function canonicalPartValue(Part $part, mixed $expected): mixed;
}
