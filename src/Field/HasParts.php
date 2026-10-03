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
 * ### A reading of a value is not a part of it
 *
 * The rule runs both ways: a value reports the parts it is **submitted with**, and a value
 * submitted as one thing reports none. `EmailAddress\Value` implemented this and returned
 * `local_part, domain` — both readable on the value, neither ever an input, since an address is
 * one box on a form. `PhoneNumber\Value` returned `country, e164` for an input of
 * `{number, country}`, so a scope resolved against something nobody had sent while the one part
 * a form definitely renders raised. Both are gone, and `Api\StructuredTypeTest` holds the line.
 *
 * The test for whether something belongs here: *could a submitter fill this in on its own?* If it
 * is derived from the parts rather than one of them, it is a method on the value —
 * `Value::toE164()`, `Value::__toString()`.
 *
 * ### The names live on the part enum
 *
 * Which parts a value has, whether one is essential and whether one holds a list are facts a rule
 * needs where it is *written*, with no request and so no value to inspect — without them,
 * `ValueScope::of('billing', 'ctry')` would be accepted at authoring time and silently resolve to
 * `null` on every request afterwards. They used to be static methods here; they are the cases and
 * methods of the value's {@see Part} enum now, read through {@see \Meraki\Schema\Field::$parts},
 * so there is one place a part is described rather than three.
 */
interface HasParts
{
	/**
	 * This value's parts, keyed by each {@see Part}'s value — `postal_code`. A part nobody
	 * supplied is present and `null` rather than missing, so reading one is never a question about
	 * whether the key exists.
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
	 * @param Part $part one of the field's {@see \Meraki\Schema\Field::$parts}
	 * @param mixed $expected whatever the rule was written with
	 */
	public function canonicalPartValue(Part $part, mixed $expected): mixed;
}
