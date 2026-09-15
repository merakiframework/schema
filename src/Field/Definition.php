<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\AtomicField;
use Meraki\Schema\Field;
use Brick\DateTime\Instant;
use InvalidArgumentException;

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
	 * Reads submitted input as the thing this field is about, or hands back `null` when it cannot.
	 *
	 * The single conversion step. It replaced `process()`, `validateValue()` and `transform()`,
	 * which between them parsed most values twice and had to agree with each other to be correct.
	 *
	 * Four things hold, and everything downstream depends on them:
	 *
	 * - **It never receives `null`.** Absence is settled before it runs — no input and no default
	 *   means there is nothing to read, so the field is skipped or reported missing without this
	 *   being called. `null` in the return therefore means one thing only: *unreadable*.
	 * - **It never raises.** It runs on attacker-controlled input, so an unreadable value is
	 *   reported, not thrown. Failing to parse is ordinary, not exceptional — throwing belongs to
	 *   definition time, where the author can act on it.
	 * - **What it returns is what the constraints see.** So a constraint is typed `Number\Value`
	 *   and has that be true by construction.
	 * - **It always returns a value object this library defines.** Never a bare scalar, and never a
	 *   third party's class — see below.
	 *
	 * It does not *repair* input. Trimming whitespace or fixing case is the port's business — see
	 * docs/CODING-STYLE.md. It canonicalises only where a standard says two spellings are one
	 * thing, and that belongs in the field's own `Value` object, not here.
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
	abstract protected function parse(mixed $value): ?ParsedValue;

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

	/**
	 * Sets the shared configuration to its defaults.
	 *
	 * Called from the using class's constructor, because a readonly property cannot carry a
	 * declaration default until PHP 8.6 — see docs/ROADMAP.md, where this call disappears.
	 */
	private function initialiseDefinition(): void
	{
		$this->optional = false;
		$this->defaultValue = null;
	}

	/**
	 * @throws InvalidArgumentException if the value could not satisfy this field
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
	 * What this field would actually validate, given what was submitted: the parsed value, or what
	 * was submitted when it could not be parsed.
	 *
	 * Keeping the unreadable input rather than discarding it is deliberate — a form redrawing a
	 * rejected field still has something meaningful to show, and the verdict says which of the two
	 * you are holding.
	 *
	 * The authored default goes through `parse()` too, so `defaultsTo('2026-01-01')` on a date
	 * yields the same `LocalDate` that submitting that string would.
	 */
	final public function resolvedValueFor(mixed $given): mixed
	{
		$raw = $given ?? $this->defaultValue;

		return $raw === null ? null : ($this->parse($raw) ?? $raw);
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
	 * @throws InvalidArgumentException if the change leaves the authored default invalid
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
	 * @throws InvalidArgumentException
	 */
	private function assertDefaultCanSatisfyIt(): void
	{
		// No default is not an invalid one.
		if ($this->defaultValue === null) {
			return;
		}

		$parsed = $this->parse($this->defaultValue);

		if ($parsed === null) {
			throw new InvalidArgumentException(sprintf(
				'The default for "%s" is not a value it can hold.',
				(string) $this->name,
			));
		}

		foreach ($this->constraints as $constraint) {
			// A question about the calendar cannot be settled at definition time: the answer
			// changes without the schema changing, so a default that passes today would throw on
			// its own at boot some years from now. Judged per request instead, like any value.
			if ($constraint->timeRelative) {
				continue;
			}

			if ($constraint->against($parsed)->failed()) {
				throw new InvalidArgumentException(sprintf(
					'The default for "%s" does not satisfy its own "%s" constraint.',
					(string) $this->name,
					$constraint->name,
				));
			}
		}
	}

}
