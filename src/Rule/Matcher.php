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
 * Only `equals` and `notEquals` exist so far. The comparison verbs — `isAtLeast`,
 * `isGreaterThan` and the rest — are in docs/ROADMAP.md, and {@see \Meraki\Schema\Field\Comparable}
 * is the half of them that is already here: each is that interface's `compareTo()` against zero,
 * so they are written once rather than once per field type.
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
}
