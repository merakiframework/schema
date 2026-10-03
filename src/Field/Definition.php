<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\AtomicField;
use Meraki\Schema\Exception\InvalidDefault;
use Meraki\Schema\Field;
use Meraki\Schema\ValueSource;
use Brick\DateTime\Instant;

/**
 * What a field *is*, and the only way to change it — everything except how it validates.
 *
 * A trait rather than a base class because the two halves vary independently. Every field shares
 * this half exactly: the same configuration, the same copy-on-change rule, the same conversion
 * hooks. What differs is the *lifecycle* — {@see AtomicField} resolves one value and checks it
 * against constraints, while {@see Collection} resolves a list and checks each item against a
 * template — and a field should be able to pick a lifecycle without inheriting the wrong name
 * for what it holds.
 *
 * Readonly throughout, so a using class must also be readonly. That is the point: one schema
 * serves many concurrent requests, and a field that could be written to during one would leak
 * into the next.
 *
 * @phpstan-require-implements Field
 */
trait Definition
{
	public readonly bool $optional;

	public readonly mixed $defaultValue;

	/**
	 * Reads submitted input as the thing this field is about, and refuses what it cannot.
	 *
	 * The single conversion step. It replaced `process()`, `validateValue()` and `transform()`,
	 * which between them parsed most values twice and had to agree with each other to be correct.
	 *
	 * Four things hold, and everything downstream depends on them:
	 *
	 * - **It never receives `null`.** Absence is settled before it runs — no input and no default
	 *   means there is nothing to read, so the field is skipped or reported missing without this
	 *   being called.
	 * - **It returns a value, or raises {@see MalformedValue}.** There is no `null`, and no
	 *   try/catch for a field author to write: who absorbs the refusal is decided by the
	 *   lifecycle, below.
	 * - **What it returns is what the constraints see.** So a constraint is typed `Number\Value`
	 *   and has that be true by construction.
	 * - **It always returns a value object this library defines.** Never a bare scalar, and never a
	 *   third party's class — see below.
	 *
	 * ### Raising, and who absorbs it
	 *
	 * This used to return `null` for unreadable input, on the grounds that failing to parse is
	 * ordinary rather than exceptional. It still is — and the field still never raises on a
	 * request, because {@see self::readable()} catches. What changed is that the *reason* now
	 * survives long enough to reach somebody who can act on it:
	 *
	 * | Asked by | Answer |
	 * | --- | --- |
	 * | A request | caught, reported as an unreadable *shape* |
	 * | `defaultsTo()`, at definition time | raised, with the reason attached |
	 *
	 * The definition-time check could only say *"the default is not a value it can hold"*,
	 * because the reason had been discarded one frame earlier — while the constraint branch
	 * beside it named the constraint that failed. The weaker message was the one whose audience
	 * could have used it.
	 *
	 * ### Most of this belongs to the value, not here
	 *
	 * A value's constructor enforces its own invariant, so "is this an email address" is asked
	 * once, by the thing that has to be one. What is left here is narrowing `mixed` to the type
	 * the value takes — a request can submit anything, and handing an array to a `string`
	 * parameter raises a `TypeError`, which is not what a lifecycle catches.
	 *
	 * Two fields keep more, and both for the same reason: the check is a fact about the *field*
	 * rather than the value. {@see Enum} owns membership of a case list it was given, and
	 * {@see Collection} reads each row against its template.
	 *
	 * It does not *repair* input. Trimming whitespace or fixing case is the port's business — see
	 * docs/CODING-STYLE.md. It canonicalises only where a standard says two spellings are one
	 * thing, and that belongs in the field's own `Value` object rather than here — an invariant
	 * the value's own `equals()` relies on has to be the value's to enforce.
	 *
	 * ### It always returns a value object
	 *
	 * That is what the return type says, and it is the fourth thing that holds. Every field defines
	 * its own value class — never a third party's, and never a bare scalar.
	 *
	 * The reason is that everything downstream eventually has to ask whether two values are the
	 * same: a collection deciding whether two rows repeat, a rule deciding whether a field holds
	 * what it is asking about, and every comparison matcher. Without this the answer came from
	 * PHP's `==`, which compares two objects property by property — so it was reading the private
	 * layout of whatever class happened to be returned. `BigDecimal` made that visibly wrong
	 * (`12.50` and `12.5` compared unequal); `LocalDate` and `Duration` gave the right answer only
	 * because of how Brick happens to store them, which is not a promise and could change in a
	 * patch release without a single test here going red.
	 *
	 * **Scalars are wrapped too**, and that is deliberate rather than reluctant. `===` on a string
	 * is already correct, so {@see Text\Value} buys nothing *by itself* — what it buys is that
	 * nothing downstream ever has to ask whether a value happens to be an object. One uniform
	 * interface is worth more than the exemption, because the exemption leaks into every consumer
	 * that compares, renders or serialises a value. The plain scalar is one property away.
	 *
	 * It also leaves somewhere to put things later. A normalising {@see Uri} and a
	 * {@see Duration} on PHP 8.6's native type are both planned in docs/ROADMAP.md, and behind a
	 * value object each is an internal change instead of a break in what `parse()` returns.
	 *
	 * There is no exemption left, including for {@see Collection} — whose value is *many* values
	 * rather than one, and which is therefore the case with the strongest claim to being special.
	 * It gets {@see Collection\Value} like everything else, because an exemption anywhere means
	 * every consumer has to ask what kind of thing it is holding before it can do anything with it.
	 *
	 * So the return type is the whole contract: a value object, or nothing.
	 *
	 * @see ParsedValue  what every returned value implements
	 * @see Comparable for the ordered ones, which the comparison matchers build on
	 */
	abstract protected function parse(mixed $value): ParsedValue;

	/**
	 * The instant this field's time-relative constraints were judged against, or `null` when it
	 * has none and so never asks what time it is.
	 *
	 * Read once per resolution and recorded on the result, which is what makes a verdict
	 * reproducible: the same input and the same instant give the same answer whenever the
	 * question is asked again. A field holds a *source* of the instant rather than an instant —
	 * see {@see \Meraki\Schema\Field\CreditCard} — so this goes and finds out rather than
	 * reporting something stored.
	 */
	protected function evaluatedAt(): ?Instant
	{
		return null;
	}

	/**
	 * Submitted input read as a **record** — a set of named parts — or `null` when it is not one.
	 *
	 * ### An object is a record; an array is a list
	 *
	 * This is the whole rule, and it is what every structured field gates on. A value with named
	 * parts — an amount and its currency, the lines of an address, a card's number and expiry —
	 * arrives as an object. A value with *many of something* — a collection's items — arrives as
	 * an array. Neither shape is accepted where the other belongs.
	 *
	 * PHP blurs this and nothing else will draw the line: an associative array and a list are the
	 * same type, so `['amount' => …]` and `[$row1, $row2]` cannot be told apart by asking. The
	 * rule puts the distinction in the *shape* of the input rather than in a guess about its keys.
	 *
	 * It buys something concrete. A collection can now key its items — `['line item 1' => …]` —
	 * because a string key on an array is no longer ambiguous with a record's field name. That was
	 * impossible while both meant "named parts".
	 *
	 * **Converting is the port's job.** `$_POST` and `$_FILES` are associative arrays throughout,
	 * and a JSON body decodes to objects only if you ask (`json_decode($body)` rather than
	 * `json_decode($body, true)`). Whichever a port starts from, it hands the core objects — see
	 * docs/CODING-STYLE.md.
	 *
	 * `get_object_vars()` from outside the object, which is deliberate: called this way it returns
	 * *public* properties only, so a value object's internals cannot be mistaken for submitted
	 * parts. It reads declared properties rather than going through `__get()`, which a field
	 * cannot enumerate anyway — an object that exposes its values only through accessors has to be
	 * converted by the caller.
	 *
	 * @return array<string, mixed>|null the named parts, or null if this was not a record
	 */
	final protected static function recordIn(mixed $value): ?array
	{
		// A stdClass — from a cast or from json_decode() — is the ordinary case.
		return is_object($value) ? get_object_vars($value) : null;
	}

	/**
	 * Built once, by the using class's constructor, and rebuilt by {@see self::with()}.
	 *
	 * Stored rather than derived on read because a `readonly` class cannot declare a property
	 * hook — not even a virtual, get-only one. The cost is that it is *derived state that has to
	 * be kept*, which is exactly what `with()` exists to guarantee.
	 */
	public readonly Constraint\Set $constraints;

	/**
	 * The checks this field makes. Called once from the constructor and again for every wither,
	 * never on read, so it may do real work — a repository lookup for a country's postcode
	 * pattern, say.
	 */
	abstract protected function defineConstraints(): Constraint\Set;

	/** @var list<Part> */
	public readonly array $parts;

	/** @var list<Part> */
	public readonly array $essentialParts;

	/** @var list<Check> */
	public readonly array $checks;

	/**
	 * Sets the shared configuration to its defaults.
	 *
	 * Called from the using class's constructor, because a readonly property cannot carry a
	 * declaration default until PHP 8.6 — see docs/ROADMAP.md, where this call disappears.
	 */
	private function initialiseDefinition(): void
	{
		$this->optional = self::initially(false);
		$this->defaultValue = self::initially(null);

		// Declarations rather than configuration: no wither changes them, so they are read once
		// from the field's own enums and every copy carries the same lists.
		$this->parts = static::declaredParts();
		$this->essentialParts = array_values(array_filter(
			$this->parts,
			static fn(Part $part): bool => $part->isEssential(),
		));
		$this->checks = static::declaredChecks();
	}

	/**
	 * The parts this field's value is made of: the cases of its {@see Part} enum, or nothing for
	 * a value that is one thing.
	 *
	 * @return list<Part>
	 */
	protected static function declaredParts(): array
	{
		return [];
	}

	/**
	 * Every code this field reports a failure under: the cases of its {@see Check} enum.
	 *
	 * @return list<Check>
	 */
	protected static function declaredChecks(): array
	{
		return [];
	}

	/**
	 * The value a configuration property starts at, handed back unchanged.
	 *
	 * It exists for the analyser, and the reason is worth the indirection. A field is a `readonly`
	 * class, so PHPStan types every property as *the value the constructor assigned it* — and it
	 * cannot see {@see self::with()}, which changes them by cloning with a string-keyed array.
	 * `public ?int $maxLength` initialised to `null` is therefore inferred as `null` and nothing
	 * else, so the `!== null` guard in front of every use of it narrows to *NEVER*: the guard is
	 * reported as always-false, its body as unreachable, and the method around it as never
	 * returning what it returns.
	 *
	 * That is one blind spot producing about a hundred errors, and it is worse than noise —
	 * suppressing it by pattern would suppress a *genuine* inverted guard written the same way.
	 * Returning `mixed` here keeps the property at its declared type, so a real type error is
	 * still caught. It is what took `src` from 101 errors at level 4 to clean at **level 6**,
	 * with three narrow ignore entries rather than two blanket patterns. Not higher: levels 7
	 * and 8 cost 47 and 50 errors respectively, for unrelated reasons tracked in
	 * docs/ROADMAP.md.
	 *
	 * **It does cost one thing.** A property that really is never configured no longer has its
	 * dead guard reported, because this makes the two cases identical to the analyser. The wither
	 * is what makes a property configuration, so the check that matters is that one exists.
	 *
	 * Delete this when PHPStan models PHP 8.5's clone-with, or when 8.6's readonly property
	 * defaults let these be declared rather than assigned. See docs/ROADMAP.md.
	 */
	protected static function initially(mixed $value): mixed
	{
		return $value;
	}

	/**
	 * @throws InvalidDefault if the value could not satisfy this field
	 */
	public function defaultsTo(mixed $value): static
	{
		return $this->with(['defaultValue' => $value]);
	}

	public function makeOptional(): static
	{
		return $this->with(['optional' => true]);
	}

	public function makeRequired(): static
	{
		return $this->with(['optional' => false]);
	}

	/**
	 * Typed rather than `mixed`, matching {@see \Meraki\Schema\Scope::equals()}: comparing a field
	 * to another field is a question, and comparing one to a string is a mistake. A `false` would
	 * answer the mistake as though it had been asked on purpose.
	 */
	public function equals(Field $other): bool
	{
		return $other instanceof static && $this->name->equals($other->name);
	}

	/**
	 * What this field would actually validate: the parsed value, or `null`.
	 *
	 * **Always valid, or nothing.** It used to hand back the submitted input when that could not be
	 * parsed, on the grounds that a form redrawing a rejected field needs something to show — but
	 * {@see \Meraki\Schema\ResolvedField::$given} already holds exactly that, unchanged, and is the
	 * right thing to echo. Keeping it here as well made `$value` a union of "the domain type" and
	 * "whatever arrived", so nothing downstream could rely on its type: a rule comparing
	 * `equals(18)` would have been handed the string `'abc'` to compare against.
	 *
	 * So the two now mean one thing each. `$given` is what was sent; this is what the field made of
	 * it, and `null` means it could make nothing of it.
	 *
	 * The authored default goes through `parse()` too, so `defaultsTo('2026-01-01')` on a date
	 * yields the same `LocalDate` that submitting that string would. It cannot fail here — a
	 * default is checked against the field's own shape where it is declared.
	 */
	final public function resolvedValueFor(mixed $given): ?ParsedValue
	{
		$raw = $this->rawFor($given);

		return $raw === null ? null : self::readable($this->parse(...), $raw);
	}

	/**
	 * What there was to read: the submission, the authored default standing in for it, or
	 * whatever this kind of field calls absence.
	 *
	 * The single answer to "nothing was submitted, now what". It was three answers, and they
	 * disagreed — `Collection` had a private copy that turned absence into an empty list while
	 * this one turned it into `null`, so a rule resolving `#/fields/lines/value` and the result
	 * for that same field reported different things about the same request.
	 *
	 * @see self::absentValue() for the one part a field is allowed to vary
	 */
	final protected function rawFor(mixed $given): mixed
	{
		return $given ?? $this->defaultValue ?? $this->absentValue();
	}

	/**
	 * What absence *means* for this kind of field, when there is no authored default either.
	 *
	 * `null` for every field that holds one value: nothing was submitted, so there is nothing to
	 * judge, and the shape check reports it missing.
	 *
	 * {@see Collection} is the exception and overrides this. A repeatable section a person left
	 * alone and a JSON client sending `[]` say the same thing, and saying it as "there are no
	 * items" lets `minCount` answer with "add at least one" rather than the blunter "this field
	 * is required". That is a difference about the *kind* of field, which is why it is a hook
	 * rather than a second implementation of {@see self::rawFor()}.
	 */
	protected function absentValue(): mixed
	{
		return null;
	}

	/**
	 * Which of the three things the judged value actually was.
	 *
	 * Only the field can finish the answer: the caller knows whether it handed over something
	 * submitted or something prefilled, and the field knows whether its own default stood in when
	 * the caller handed over nothing.
	 *
	 * Lives here rather than on each lifecycle because the two copies were byte-identical, and
	 * because it reads {@see self::$defaultValue}, which is this trait's to know about.
	 */
	protected function sourceOf(mixed $given, ValueSource $givenAs): ValueSource
	{
		if ($given !== null) {
			return $givenAs;
		}

		return $this->defaultValue === null ? ValueSource::None : ValueSource::Default;
	}

	/**
	 * Runs a parse on the request path, where an unreadable value is an answer.
	 *
	 * The one place a {@see MalformedValue} becomes a `null`, and the reason a field author
	 * never writes a try/catch: which failures are absorbed and which are raised is a
	 * lifecycle decision, and a field getting it differently from its neighbours would make
	 * results inconsistent across a schema — see docs/FIELD-API.md.
	 *
	 * @param callable(mixed): ParsedValue $parse
	 */
	final protected static function readable(callable $parse, mixed $raw): ?ParsedValue
	{
		try {
			return $parse($raw);
		} catch (MalformedValue) {
			return null;
		}
	}

	/**
	 * The same, for a rule outcome to apply.
	 *
	 * Public where {@see self::with()} is protected, and the difference is where the values came
	 * from. These are not raw properties an author typed: {@see \Meraki\Schema\Rule\Outcome\Reconfigure}
	 * reads them off a field the author configured *through its own withers*, so each one has
	 * already been past whatever that wither checks. What arrives here is a replay of work already
	 * validated, which is why it does not need validating again — and why this is not a general
	 * way to set properties on a field.
	 *
	 * It still goes through `with()`, so the constraints are rebuilt and the authored default is
	 * re-checked exactly as a wither would.
	 *
	 * @param array<string, mixed> $changes
	 * @throws InvalidDefault if the change leaves the authored default invalid
	 */
	final public function reconfiguredWith(array $changes): static
	{
		return $this->with($changes);
	}

	/**
	 * Hands back a copy with the given properties changed — the only way a field is ever
	 * configured, since writing to one is a fatal.
	 *
	 * Every wither goes through here so the authored default is re-checked each time, which is
	 * what lets it be checked at all: configuration arrives in any order, so a default set before
	 * the constraint that rejects it has to fail on the *later* call. A wither that clones
	 * directly skips that and leaves a stale default behind.
	 *
	 * @param array<string, mixed> $changes
	 * @throws InvalidDefault if the change leaves the authored default invalid
	 */
	final protected function with(array $changes): static
	{
		$field = clone($this, $changes);

		// Two clones, because $constraints is derived from the very properties $changes just
		// replaced and cannot be computed before the object holding them exists. Skipping this
		// would leave a field reporting the bounds it had *before* the wither — the one real
		// hazard of storing derived state, closed in the single place every wither funnels
		// through.
		$field = clone($field, ['constraints' => $field->defineConstraints()]);

		// After the rebuild: the check reads $this->constraints, and must see the new ones.
		$field->assertDefaultCanSatisfyIt();

		return $field;
	}

	/**
	 * An authored default is trusted by construction rather than by assumption: it is checked
	 * where it is written, so a request never has to re-check it.
	 *
	 * @throws InvalidDefault
	 */
	private function assertDefaultCanSatisfyIt(): void
	{
		// No default is not an invalid one.
		if ($this->defaultValue === null) {
			return;
		}

		// Not absorbed, unlike the request path. An author can act on *why* their default is
		// unreadable, and this used to be the one message that could not say — while the
		// constraint branch below it named the constraint that failed.
		try {
			$parsed = $this->parse($this->defaultValue);
		} catch (MalformedValue $malformed) {
			throw InvalidDefault::isNotAValueTheFieldCanHold((string) $this->name, $malformed);
		}

		foreach ($this->constraints as $constraint) {
			// A question about the calendar cannot be settled at definition time: the answer
			// changes without the schema changing, so a default that passes today would throw on
			// its own at boot some years from now. Judged per request instead, like any value.
			if ($constraint->timeRelative) {
				continue;
			}

			if ($constraint->against($parsed)->failed()) {
				throw InvalidDefault::failsItsOwnConstraint((string) $this->name, $constraint->name);
			}
		}
	}

}
