<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Matcher;

use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\Draft;

/**
 * The five questions a value with an order can answer.
 *
 * Offered only by a matcher whose field parses to a {@see \Meraki\Schema\Comparison\Comparable} —
 * number, date, date-time, time, duration and money. On anything else these methods do not exist,
 * so `$username->when()->isAtLeast(3)` is a call to an undefined method rather than a rule that
 * looks sensible and can never fire.
 *
 * ### Narrowing the bound
 *
 * The signatures here are as wide as the question allows. A field whose bound is hard to guess —
 * `Money` wants `{currency, amount}`, not a number — can do better by declaring its own matcher
 * that omits this trait and writes the five methods with its own type:
 *
 *     final class Money\Matcher implements Rule\Matcher
 *     {
 *         use AsksAnything;   // but not AsksOrder
 *
 *         public function isAtLeast(Money\Value|Scope|null $bound): Draft { … }
 *     }
 *
 * That is legal because these are traits. A base class declaring `isAtLeast(mixed)` would forbid
 * it — PHP allows *widening* a parameter in a subclass and refuses narrowing — which is the whole
 * reason the verbs are not on a shared parent.
 */
trait AsksOrder
{
	/**
	 * Holds when the value is the bound or anything after it. **Inclusive.**
	 *
	 *     $age->when()->isAtLeast(18)->then($contract->makeRequired());
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
	 *     $age->when()->isBetween(18, 65)->then($guardianConsent->makeOptional());
	 */
	public function isBetween(mixed $atLeast, mixed $atMost): Draft
	{
		return new Draft(new Condition\IsBetween($this->scope, $atLeast, $atMost));
	}
}
