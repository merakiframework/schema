<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Meraki\Schema\Comparison\Order;

/**
 * Holds when the value is the bound or anything after it.
 *
 * The inclusive one, and the one a minimum is written with: `isAtLeast(18)` on an age field
 * accepts 18. {@see IsGreaterThan} is its exclusive twin, and they are named so that neither can
 * be read as the other.
 *
 *     $schema->when($age)->isAtLeast(18)->thenRequire($contract);
 *
 * See {@see Ordered} for what happens when the value has no order, and {@see Comparison} for how
 * the bound is read — it goes through the same field the submitted value did, so `isAtLeast(18)`
 * works on a field that parses to a `BigDecimal`.
 */
final class IsAtLeast extends Ordered
{
	protected function holdsWhen(Order $order): bool
	{
		return $order->isAtLeast();
	}
}
