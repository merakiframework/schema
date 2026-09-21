<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Scope;

/**
 * Names what a rule is asking about, and offers the questions that can be asked of it.
 *
 * One half of the authoring vocabulary: a matcher holds a {@see Scope} and nothing else, and each
 * of its methods turns that scope plus an expected value into a {@see Condition}. The other half
 * is {@see Draft}, which is what a condition becomes once outcomes are attached.
 *
 *     $plan->when()->equals('pro')->then($billingAddress->makeRequired())
 *
 * ### Only the questions the field can answer
 *
 * This is an interface with no methods, and there are four implementations — one per set of
 * questions a value can actually answer:
 *
 * | Matcher | Offers | Fields |
 * | --- | --- | --- |
 * | {@see Matcher\Basic} | the five that work on anything | Address, Boolean, Collection, CreditCard, EmailAddress, Enum, File, Password |
 * | {@see Matcher\Ordered} | + `isAtLeast` and the rest | Money |
 * | {@see Matcher\Text} | + `contains`, `matches` | Name, PhoneNumber, Text, Uri, Uuid |
 * | {@see Matcher\OrderedText} | all twelve | Number, Date, DateTime, Time, Duration |
 *
 * {@see \Meraki\Schema\Field::when()} returns the one its value has earned, so `$text->when()`
 * has no `isAtLeast` **to offer** — it does not appear in completion and does not compile. That is
 * the difference between this and a single matcher with a `mixed` bound, which can only refuse the
 * same mistake once the rule is being added.
 *
 * The verbs live in traits rather than on a base class on purpose. A base class would fix every
 * bound at its widest, and PHP forbids *narrowing* a parameter through inheritance — so a field
 * wanting `isAtLeast(Money\Value|Scope|null)` rather than `mixed` could never say so. Composed
 * from traits, each matcher declares its own signatures and a field is free to go further. See
 * {@see Matcher\AsksOrder}.
 *
 * ### A scope on its own gets everything
 *
 * {@see \Meraki\Schema\Facade::when()} answers with {@see Matcher\OrderedText}, because a field
 * named by a string — or a part of a value — cannot be resolved to a type at authoring time. The
 * rule is still checked when it is added, so `$schema->when('notes')->isAtLeast(3)` is refused
 * there rather than silently never firing. Holding the field is what buys the earlier answer.
 *
 * Reading as a sentence is the point, but it is not the only one. Every matcher corresponds to a
 * condition class and a serialized `type`, so a rule written this way can be written to JSON and
 * rebuilt — or translated to client-side JavaScript — which a closure-backed condition never
 * could. Adding a matcher means adding a condition class and a serializer case; the rule engine
 * does not change.
 */
interface Matcher
{
	/** What the rule is asking about. */
	public Scope $scope { get; }
}
