<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Meraki\Schema\Comparison\Order;

/**
 * Holds when the value is the bound or anything before it.
 *
 * The inclusive one, and the one a maximum is written with: `isAtMost(10)` accepts 10.
 * {@see IsLessThan} is its exclusive twin.
 *
 *     $quantity->when()->isAtMost(10)->then($bulkReference->makeOptional());
 *
 * See {@see Ordered} for what happens when the value has no order, and {@see Comparison} for how
 * the bound is read.
 */
final class IsAtMost extends Ordered
{
	protected function holdsWhen(Order $order): bool
	{
		return $order->isAtMost();
	}
}
