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
 * | {@see Matcher\Basic} | the five that work on anything | Address, Boolean, Collection, CreditCard, File, Password |
 * | {@see Matcher\Ordered} | + `isAtLeast` and the rest | Money |
 * | {@see Matcher\Text} | + `contains`, `matches` | EmailAddress, Enum, Name, PhoneNumber, Text, Uri, Uuid |
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
 * {@see \Meraki\Schema\Definition::when()} answers with {@see Matcher\OrderedText}, because a field
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

	/**
	 * Set when this matcher asks about every row of a collection rather than about one value;
	 * `null` for an ordinary one.
	 *
	 * Declared here so the type checker enforces it. {@see Matcher\BuildsDrafts} reads it to
	 * decide whether to wrap a condition in {@see Condition\Quantified}, and it used to be an
	 * unwritten convention that every matcher happened to have one — a matcher of somebody
	 * else's without it emitted `Undefined property` and produced a rule that never fired.
	 * A class that does not declare it now fails to compile.
	 */
	public ?Quantifier $quantifier { get; }

	/**
	 * The same matcher, asking about a *column* of a collection instead of about one value, and
	 * folding the rows with the given quantifier.
	 *
	 * What {@see \Meraki\Schema\Field\Collection::whereAny()} and `whereEvery()` are built on.
	 * It is declared here because it is a contract, and it used to be a convention:
	 * `acrossRows()` reconstructed the matcher by calling `new ($matcher::class)($scope, $how)`,
	 * relying on a two-argument constructor this interface never mentioned. All four built-in
	 * matchers happen to have one, so it worked — and PHP passes extra arguments to a
	 * user-defined constructor without complaint, so a matcher of somebody else's that declared
	 * only `__construct(Scope $scope)` did not fail. It dropped the quantifier, built an
	 * unquantified condition against a column scope, and produced a rule that was accepted by
	 * every check and could never fire.
	 *
	 * So the claim that "a field type this library has never heard of is quantifiable the moment
	 * it picks a matcher" is true now, and was not. A matcher that cannot be quantified can also
	 * say so, by raising here.
	 *
	 * {@see Matcher\BuildsDrafts} implements this, so a matcher using that trait — as all four
	 * built-in ones do — has it already.
	 */
	public function quantifiedAt(Scope $scope, Quantifier $how): static;
}
