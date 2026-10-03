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
 * A rule about one part reads this too. "When the billing country is AU" is a question a
 * half-filled address can answer, so a part scope reads the input rather than the value. See
 * {@see \Meraki\Schema\ScopeResolver}.
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
interface Input extends HasParts
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
}
