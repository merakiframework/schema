<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

/**
 * What a field turns submitted input into.
 *
 * Named for the role rather than the method, because the role is the point: this *is* the return
 * type of {@see Definition::parse()}, and every field has one value class of its own. Equality is
 * what the role guarantees, not what it is — there is exactly one method here because that is the
 * one thing every value in the library must be able to answer about itself.
 *
 * Being the signature is what makes it hold. A field cannot parse to a bare `string`, or to a class
 * from a dependency, without failing to compile — so nothing downstream ever has to ask what kind
 * of thing it is holding before it can compare, render or serialise it.
 *
 * ### Why not just compare the objects
 *
 * PHP's `==` compares two objects property by property, which means it reads the *private layout*
 * of whatever the value happens to be. For a third-party class that is not a contract, it is an
 * implementation detail that happens to be visible:
 *
 * - `BigDecimal::of('12.50') == BigDecimal::of('12.5')` is **false**, because a `BigDecimal` keeps
 *   the scale it was given. The same money, twice, called different.
 * - `LocalDate` and `Duration` compare correctly today — but only because one stores three integers
 *   and the other normalises to seconds. Nothing promises that. A memoised formatted string added
 *   to either class in some future release would silently make equal dates unequal, with no change
 *   to any code here and no test that would obviously break.
 *
 * So relying on `==` is relying on somebody else's field list. Asking the value is the fix, and
 * requiring the value to be one this library defines is what makes asking always possible.
 *
 * ### What builds on it
 *
 * Conditions compose from this rather than reimplementing comparison: {@see \Meraki\Schema\Rule\Condition\Equals}
 * is `equals()`, and `NotEquals` is the same call negated. One primitive, two conditions, and no
 * way for them to disagree. {@see Comparable} adds ordering on the same principle.
 *
 * The parameter is the interface rather than `self` because PHP treats a narrower parameter type in
 * an implementation as an LSP violation — so an implementation checks the class itself, the same
 * way {@see \Meraki\Schema\Field::equals()} does.
 */
interface ParsedValue
{
	/**
	 * Whether `$other` is the same value as this one.
	 *
	 * Never raises, and never assumes: a value of an entirely different kind is simply not equal.
	 * Unlike {@see Comparable::compareTo()}, which refuses to order two unrelated things, "is a date
	 * the same as a duration" has an obvious answer and it is `false`.
	 */
	public function equals(self $other): bool;
}
