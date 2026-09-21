<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Matcher;

use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Scope;

/**
 * Identity, presence and order, for a value that can be ranked but has no string form.
 *
 * Returned by: Money.
 *
 * @see Matcher for why there are four of these rather than one, and why the verbs live in traits.
 */
final readonly class Ordered implements Matcher
{
	use AsksAnything;
	use AsksOrder;
	public function __construct(public Scope $scope)
	{
	}
}
