<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

/**
 * A parsed value made of named parts, so a scope can read into it.
 *
 * `#/fields/billing_address/value` is the whole address; `#/fields/billing_address/value/country`
 * is one part of it. This interface is what tells the two apart, and what makes the second
 * answerable: a value that does not implement it has nothing inside worth addressing, and a scope
 * asking for a part of one is refused where the rule is written.
 *
 * It is what lets a rule ask the questions a form actually has:
 *
 *     // is the whole shipping address the billing address?
 *     $schema->when($shipping)->equals(ValueScope::of('billing'))
 *
 *     // are they at least in the same country?
 *     $schema->when(ValueScope::of('shipping', 'country'))->equals(ValueScope::of('billing', 'country'))
 *
 * ### The part names are the ones already in use
 *
 * A part is named as it arrives and as it is reported: `postal_code`, not `postalCode`. Those are
 * the keys {@see Definition::recordIn()} reads from submitted input and the values a constraint
 * carries as {@see Constraint::$part}, so a consumer that has seen either already knows this
 * vocabulary. A third spelling for the same thing would be the dotted-name problem again in
 * miniature.
 *
 * ### Why the names are declarable without a value
 *
 * {@see self::partNames()} is static because {@see \Meraki\Schema\Facade::addRule()} validates a
 * scope when the rule is *written*, where there is no request and so no value to inspect. Without
 * it, `ValueScope::of('billing', 'ctry')` would be accepted at authoring time and silently resolve
 * to `null` on every request afterwards — which is the failure mode this library spends most of
 * its guards avoiding.
 */
interface HasParts
{
	/**
	 * Every part this kind of value has, whether or not any of them were submitted.
	 *
	 * Static, so a scope can be checked against a field before any request exists.
	 *
	 * @return list<string>
	 */
	public static function partNames(): array;

	/**
	 * This value's parts, by name. A part nobody supplied is present and `null` rather than
	 * missing, so reading one is never a question about whether the key exists.
	 *
	 * @return array<string, mixed>
	 */
	public function parts(): array;

	/**
	 * The value this part would hold, had the given spelling been submitted.
	 *
	 * A value canonicalises what it is given, and a rule has to compare against what was
	 * *stored*: an address holds `AU-QLD` whichever of `QLD`, `qld`, `AU-QLD` or `Queensland`
	 * arrived, and `AU` for `Australia`. Without this, `equals('QLD')` compared the stored
	 * `AU-QLD` against `QLD`, was false for every request there would ever be, and said nothing
	 * — a rule written in the very spelling the field accepts as input.
	 *
	 * Asked of the value rather than worked out by the comparison, because only the value knows
	 * what it did with the input. A subdivision needs its country to resolve, and the submitted
	 * value is the only thing holding one.
	 *
	 * Return the expectation unchanged for a part that is stored as it arrives, which is most of
	 * them — {@see \Meraki\Schema\Field\Money\Value} canonicalises nothing, so it returns what it
	 * was handed.
	 *
	 * @param string $part one of {@see self::partNames()}
	 * @param mixed $expected whatever the rule was written with
	 */
	public function canonicalPartValue(string $part, mixed $expected): mixed;

	/**
	 * Which of {@see self::partNames()} hold a *list* rather than one string.
	 *
	 * Static, for the same reason `partNames()` is: a rule is checked when it is written, where
	 * there is no value to inspect. `Address\Value::$street` is the only one today — an address
	 * line is one of up to three, and nothing here joins them into delimited text.
	 *
	 * What it buys is an authoring-time refusal. A list has no order, so `isAtLeast(3)` against
	 * one has no meaning to give it; without this it was accepted and then never fired, which is
	 * indistinguishable from a condition that simply never held. The textual verbs fold over the
	 * entries instead — see {@see \Meraki\Schema\Rule\Condition\Textual::textLinesAt()}.
	 *
	 * @return list<string>
	 */
	public static function listParts(): array;
}
