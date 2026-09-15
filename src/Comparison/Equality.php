<?php
declare(strict_types=1);

namespace Meraki\Schema\Comparison;

/**
 * A value that knows what counts as being the same value.
 *
 * The capability, separate from any role. {@see \Meraki\Schema\Field\ParsedValue} extends it
 * because everything a field parses to must answer this — but the question "are these the same
 * value" is not a question about fields, so it lives here rather than in `Field\`.
 *
 * ### Why not just compare the objects
 *
 * PHP's `==` compares two objects property by property, which means it reads the *private layout*
 * of whatever the value happens to be. For a third-party class that is not a contract, it is an
 * implementation detail that happens to be visible:
 *
 * - `BigDecimal::of('12.50') == BigDecimal::of('12.5')` is **false**, because a `BigDecimal` keeps
 *   the scale it was given. The same number, twice, called different.
 * - `LocalDate` and `Duration` compare correctly today — but only because one stores three integers
 *   and the other normalises to seconds. Nothing promises that. A memoised formatted string added
 *   to either class in some later release would silently make equal dates unequal, with no change
 *   to any code here and no test that would obviously break.
 *
 * So relying on `==` is relying on somebody else's field list. Asking the value is the fix, and
 * requiring the value to be one this library defines is what makes asking always possible.
 *
 * ### Fields do not implement this
 *
 * A field is a *definition*, and {@see \Meraki\Schema\Field::equals()} already means something
 * else entirely — "the same field, by name". A rule comparing two fields compares the values they
 * resolved to, never the definitions, so there is nothing a field would use this for and two
 * meanings of `equals()` on one class to avoid.
 *
 * The parameter is the interface rather than `self` in implementations, because PHP treats a
 * narrower parameter type as an LSP violation — so an implementation checks the class itself, the
 * same way {@see \Meraki\Schema\Field::equals()} does.
 */
interface Equality
{
	/**
	 * Whether `$other` is the same value as this one.
	 *
	 * Never raises, and never assumes: a value of an entirely different kind is simply not equal.
	 * Unlike {@see Comparable::compareTo()}, which refuses to order two unrelated things, "is a
	 * date the same as a duration" has an obvious answer and it is `false`.
	 */
	public function equals(self $other): bool;
}
