<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Scope;

/**
 * Names what a rule is asking about, and offers the questions that can be asked of it.
 *
 * One half of the authoring vocabulary: a matcher holds a {@see Scope} and nothing else, and
 * each of its methods turns that scope plus an expected value into a {@see Condition}. The
 * other half is {@see Draft}, which is what a condition becomes once outcomes are attached.
 *
 *     $schema->when($plan)->equals('pro')->thenRequire($billingAddress)
 *
 * ### The vocabulary
 *
 * | Matcher | Holds when | Subject |
 * | --- | --- | --- |
 * | {@see self::equals()} | it is that value | any |
 * | {@see self::notEquals()} | it is anything else | any |
 * | {@see self::isAtLeast()} | it is that or after — inclusive | ordered |
 * | {@see self::isGreaterThan()} | it is strictly after | ordered |
 * | {@see self::isAtMost()} | it is that or before — inclusive | ordered |
 * | {@see self::isLessThan()} | it is strictly before | ordered |
 * | {@see self::isBetween()} | it is within both, inclusive | ordered |
 * | {@see self::isIn()} | it is any one of those | any |
 * | {@see self::contains()} | its text holds that text | text |
 * | {@see self::matches()} | its text matches that pattern | text |
 * | {@see self::isEmpty()} | nothing was submitted for it | any |
 * | {@see self::isNotEmpty()} | something was | any |
 *
 * *Ordered* means a value with an order: numbers, dates, times, durations and money. A rule asking
 * where a text field sits is refused where it is written rather than never firing — see
 * {@see Condition\Ordered}.
 *
 * One spelling per matcher. There is no `whenItIsAtLeast()` shorthand alongside `isAtLeast()`,
 * because two names for one thing is precisely what docs/API.md exists to remove.
 *
 * Reading as a sentence is the point, but it is not the only one. Every matcher corresponds to
 * a condition class and a serialized `type`, so a rule written this way can be written to JSON
 * and rebuilt — or translated to client-side JavaScript — which a closure-backed condition
 * never could. Adding a matcher means adding a condition class and a serializer case; the rule
 * engine does not change.
 */
final readonly class Matcher
{
	public function __construct(public Scope $scope)
	{
	}

	/**
	 * Holds when the value is exactly the one given.
	 */
	public function equals(mixed $expected): Draft
	{
		return new Draft(new Condition\Equals($this->scope, $expected));
	}

	/**
	 * Holds when the value is anything but the one given.
	 */
	public function notEquals(mixed $expected): Draft
	{
		return new Draft(new Condition\NotEquals($this->scope, $expected));
	}

	/**
	 * Holds when the value is the bound or anything after it. **Inclusive.**
	 *
	 *     $schema->when($age)->isAtLeast(18)->thenRequire($contract);
	 */
	public function isAtLeast(mixed $bound): Draft
	{
		return new Draft(new Condition\IsAtLeast($this->scope, $bound));
	}

	/**
	 * Holds when the value is strictly after the bound. **Exclusive.**
	 */
	public function isGreaterThan(mixed $bound): Draft
	{
		return new Draft(new Condition\IsGreaterThan($this->scope, $bound));
	}

	/**
	 * Holds when the value is the bound or anything before it. **Inclusive.**
	 */
	public function isAtMost(mixed $bound): Draft
	{
		return new Draft(new Condition\IsAtMost($this->scope, $bound));
	}

	/**
	 * Holds when the value is strictly before the bound. **Exclusive.**
	 *
	 * The matcher `Date::until()` corresponds to, which is exclusive for the same reason.
	 */
	public function isLessThan(mixed $bound): Draft
	{
		return new Draft(new Condition\IsLessThan($this->scope, $bound));
	}

	/**
	 * Holds when the value sits within both bounds. **Inclusive at both ends**, because it is
	 * {@see self::isAtLeast()} and {@see self::isAtMost()} together.
	 *
	 *     $schema->when($age)->isBetween(18, 65)->thenMakeOptional($guardianConsent);
	 */
	public function isBetween(mixed $atLeast, mixed $atMost): Draft
	{
		return new Draft(new Condition\IsBetween($this->scope, $atLeast, $atMost));
	}

	/**
	 * Holds when the value is any one of those given.
	 *
	 *     $schema->when($country)->isIn(['AU', 'NZ'])->thenRequire($gstNumber);
	 *
	 * @param list<mixed> $candidates
	 */
	public function isIn(array $candidates): Draft
	{
		return new Draft(new Condition\IsIn($this->scope, $candidates));
	}

	/**
	 * Holds when the value's text contains the given text. Case-sensitive; use
	 * {@see self::matches()} with an `i` flag when it should not be.
	 */
	public function contains(string $needle): Draft
	{
		return new Draft(new Condition\Contains($this->scope, $needle));
	}

	/**
	 * Holds when the value's text matches the given pattern — a PCRE with its delimiters, the
	 * same thing `Text::matching()` takes.
	 */
	public function matches(string $pattern): Draft
	{
		return new Draft(new Condition\Matches($this->scope, $pattern));
	}

	/**
	 * Holds when nothing was submitted for the field, or nothing readable was.
	 *
	 * Not the same as `equals(null)`: a null expectation means the field's *authored default*,
	 * deliberately, so on a field with one they ask different questions. See
	 * {@see Condition\Emptiness}.
	 */
	public function isEmpty(): Draft
	{
		return new Draft(new Condition\IsEmpty($this->scope));
	}

	/**
	 * Holds when something was submitted for the field.
	 */
	public function isNotEmpty(): Draft
	{
		return new Draft(new Condition\IsNotEmpty($this->scope));
	}
}
