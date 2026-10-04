<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Exception\InvalidDefault;
use Meraki\Schema\Exception\UnknownField;
use Meraki\Schema\Field\Constraint;

/**
 * What every field is, regardless of how it validates.
 *
 * Split from {@see AtomicField} because the two are not the same promise. This says what a
 * field *offers*; {@see AtomicField} is one implementation of it — the ordinary "one value,
 * checked against constraints" lifecycle that nearly every field wants. A field whose value
 * is not one of those implements this directly instead: {@see Field\Collection} holds a list and
 * replaces `resolve()` and `validate()` wholesale, so inheriting a lifecycle it overrides
 * entirely bought nothing.
 *
 * Every member is `{ get; }` only, so an implementation may be `readonly` — and readonly is
 * what makes a schema safe to share across concurrent requests. Get-only also means an
 * implementation may *narrow* a type: `Field::$defaultValue` is `mixed` here, and a text
 * field is free to declare it `?string`.
 *
 * @template AcceptedType of mixed = mixed
 */
interface Field
{
	/**
	 * Identifies the field within a schema, and is unique there.
	 */
	public FieldName $name { get; }

	/**
	 * Whether the field will accept no input at all.
	 */
	public bool $optional { get; }

	/**
	 * The constant the author wrote for when nothing is submitted; `null` when there is none.
	 *
	 * A default of null and no default at all are the same thing, because null is exactly
	 * what "nothing was provided" already means — there is nothing for the distinction to
	 * change.
	 *
	 * This holds only what the author typed. A value fetched for one user is a *prefill*,
	 * passed per request, and never reaches the definition — that separation is what keeps a
	 * definition free of user data, however many requests it is shared across.
	 *
	 * @var AcceptedType|null
	 */
	public mixed $defaultValue { get; }

	/**
	 * Sets the constant to fall back on when nothing is submitted.
	 *
	 * The value is checked against this field's own shape and constraints as it is declared,
	 * so `defaultsTo(0)` on a field with a minimum of 1 throws where the author wrote it
	 * rather than surfacing later as a failure on somebody's request.
	 *
	 * @param AcceptedType|null $value
	 * @throws InvalidDefault if the value could not satisfy this field
	 */
	public function defaultsTo(mixed $value): static;

	public function makeOptional(): static;

	public function makeRequired(): static;

	public function equals(self $other): bool;

	/**
	 * The same field with properties a rule outcome recorded put back.
	 *
	 * Not a general setter. The values come from {@see Rule\Outcome\Reconfigure}, which read them
	 * off a field the author configured through this field\u0027s own withers — so each has already
	 * been past whatever that wither checks. See {@see Field\Definition::reconfiguredWith()}.
	 *
	 * @param array<string, mixed> $changes
	 */
	public function reconfiguredWith(array $changes): static;

	/**
	 * The questions a rule may ask about this field.
	 *
	 *     $age->when()->isAtLeast(18)->then($contract->makeRequired())
	 *
	 * Declared here as {@see Rule\Matcher} — the marker — and narrowed by each field to the one
	 * its value has earned, which is ordinary return covariance. So `$text->when()` has no
	 * `isAtLeast` **to offer**: it is absent from completion and does not compile, rather than
	 * being a call that looks sensible and is refused later.
	 *
	 * Nothing should hold the return of this method as a bare `Rule\Matcher`. Doing so throws away
	 * the narrowing, which is the entire point — hold the field instead, and the type follows.
	 *
	 * {@see \Meraki\Schema\Definition::when()} is the other way in, for a field named by string or a
	 * scope pointing at a part. It cannot know the type, so it answers with every verb and leans
	 * on the check that runs when the rule is added.
	 */
	public function when(): Rule\Matcher;

	/**
	 * Resolves a submitted value against this field, without checking it.
	 *
	 * This is the seam: the one place a value meets a field. Nothing is written back, so the
	 * field is unchanged and safe to share — resolving the same field concurrently with
	 * different values cannot interfere.
	 *
	 * The result is {@see ValidationStatus::Pending}: a form is rendered before it is
	 * submitted, and that state needs a name.
	 *
	 * @param AcceptedType|null $given exactly what was submitted, or null if nothing was
	 * @param list<Rule\AppliedOutcome> $appliedOutcomes rules that altered this field
	 * @param ValueSource $givenAs where `$given` came from. The field settles the rest itself —
	 *        it owns the default, so it is the only thing that can say whether one stood in.
	 */
	public function resolve(
		mixed $given,
		array $appliedOutcomes = [],
		ValueSource $givenAs = ValueSource::Submitted,
	): AggregatedValidationResult;

	/**
	 * Resolves a submitted value and checks it against this field's constraints.
	 *
	 * @param AcceptedType|null $given
	 * @param list<Rule\AppliedOutcome> $appliedOutcomes
	 * @param ValueSource $givenAs where `$given` came from
	 * @param PrefillPolicy $policy whether a value that survives as {@see ValueSource::Prefilled}
	 *        still has to satisfy the constraints
	 */
	public function validate(
		mixed $given,
		array $appliedOutcomes = [],
		ValueSource $givenAs = ValueSource::Submitted,
		PrefillPolicy $policy = PrefillPolicy::Checked,
	): AggregatedValidationResult;

	/**
	 * This field's result among a schema's — or a collection row's — found by the field rather than
	 * by a string.
	 *
	 *     $billing->resultIn($schema->validate($data))->forPart(Address\Part::Country);
	 *
	 * `forField('billing')` can only promise the base result type, because PHP cannot narrow a
	 * return by the value of an argument. A field can: a field with a result of its own narrows
	 * this method's return type — {@see Field\Password::resultIn()} hands back a
	 * {@see Field\Password\Result} — which is the same move {@see self::when()} makes for rules.
	 * It is also a typo that cannot happen, because there is no name to misspell.
	 *
	 * @throws UnknownField when the results hold nothing for this field
	 */
	public function resultIn(SchemaValidationResult|Field\Collection\Item $results): FieldResult;

	/**
	 * What this field would actually validate: the parsed value, or `null` when there is none.
	 *
	 * Always valid, or nothing. It never hands back input it could not read — that is on
	 * {@see ResolvedField::$given}, unchanged, which is the thing to echo into a form being
	 * redrawn. Keeping it here as well made `$value` a union of "the domain type" and "whatever
	 * arrived", so nothing downstream could rely on its type.
	 *
	 * For a value made of parts it is the assembled value, so it is `null` while the parts make
	 * none — see {@see self::resolvedInputFor()} for the parts themselves.
	 *
	 * @param AcceptedType|null $given
	 */
	public function resolvedValueFor(mixed $given): ?Field\ParsedValue;

	/**
	 * The parts of what this field was given, as read, whether or not they make a value; `null`
	 * when nothing readable arrived, or when the value is one thing with no parts to read
	 * separately.
	 *
	 * What a rule about one part reads, so "when the billing country is AU" holds on an address
	 * whose street is still empty. See {@see Field\Input}.
	 *
	 * @param AcceptedType|null $given
	 */
	public function resolvedInputFor(mixed $given): ?Field\Input;

	/**
	 * Whether `$given` says nothing at all, so that a prefill or the authored default may stand in
	 * for it.
	 *
	 * `null` says nothing to every field. To a field whose value has parts, so does a record with
	 * no part in it — `{}`, or every part `null` — because a form that renders one box per part
	 * and was left alone submits exactly that. Reading it as an attempt made it *unreadable*, so a
	 * prefill lost to it and an optional field failed for being left empty.
	 *
	 * Asked of the field rather than worked out by its caller, because the field is what knows its
	 * parts. A record holding a key the value does not have is never nothing, even when that key is
	 * `null`: it is read, and refused for the key — see {@see Exception\BrokenInputContract}.
	 *
	 * @param AcceptedType|null $given
	 */
	public function treatsAsAbsent(mixed $given): bool;

	/**
	 * Every part this field's value is made of, in the order a value is written — empty for a
	 * value that is one thing.
	 *
	 * The cases of the value's own {@see Field\Part} enum, so a port can draw one input per part
	 * and a rule can name one, before anything is submitted.
	 *
	 * @var list<Field\Part>
	 */
	public array $parts { get; }

	/**
	 * The parts no value of this kind can exist without, whatever this field asks for.
	 *
	 * A fact about the kind of value rather than configuration: a phone number needs its number
	 * and its country on every field there will ever be, so no wither adds to this or takes from
	 * it. A part a field merely *demands* — an address's street, above its precision floor — is
	 * not here, because another field may decline it.
	 *
	 * On a field that is optional, these are required together or not at all: sending nothing is
	 * fine, and sending anything needs all of them.
	 *
	 * @var list<Field\Part>
	 */
	public array $essentialParts { get; }

	/**
	 * Every code this field can report a failure under, besides the missing and unreadable shapes
	 * every field shares.
	 *
	 * The cases of the field's own {@see Field\Check} enum. Listed so a language pack, a port or a
	 * test can know every failure a field may produce without validating anything.
	 *
	 * @var list<Field\Check>
	 */
	public array $checks { get; }

	/**
	 * The checks this field makes, each carrying the name it reports under, the part of a
	 * structured value it concerns, and the bound a message needs.
	 *
	 * A property because it reads state rather than asking a question — see docs/CODING-STYLE.md.
	 * Declared `{ get; }` so an implementation may satisfy it with a plain stored property, which
	 * is the only option open to a `readonly` class: PHP refuses hooks there, virtual ones
	 * included. The set is therefore built once and rebuilt by every wither.
	 */
	public Constraint\Set $constraints { get; }
}
