<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Meraki\Schema\Comparison\Order;

/**
 * Holds when the value is strictly before the bound.
 *
 * The exclusive one: `isLessThan('2030-01-01')` on a date field rejects that date itself.
 * {@see IsAtMost} is the inclusive twin.
 *
 * Worth noticing that this is the matcher `Date::until()` corresponds to, which is also exclusive
 * and named to say so — the rule surface and the field surface agree about where a range ends.
 *
 * See {@see Ordered} for what happens when the value has no order, and {@see Comparison} for how
 * the bound is read.
 */
final class IsLessThan extends Ordered
{
	protected function holdsWhen(Order $order): bool
	{
		return $order->isLess();
	}
}
