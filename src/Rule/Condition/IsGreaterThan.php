<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Meraki\Schema\Comparison\Order;

/**
 * Holds when the value is strictly after the bound.
 *
 * The exclusive one: `isGreaterThan(0)` on a quantity field rejects 0. {@see IsAtLeast} is the
 * inclusive twin, and reaching for the wrong one is the commonest mistake in this pair — which is
 * why they are spelled as differently as they are.
 *
 *     $balance->when()->isGreaterThan(0)->then($payoutMethod->makeRequired());
 *
 * See {@see Ordered} for what happens when the value has no order, and {@see Comparison} for how
 * the bound is read.
 */
final class IsGreaterThan extends Ordered
{
	protected function holdsWhen(Order $order): bool
	{
		return $order->isGreater();
	}
}
