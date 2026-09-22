<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Matcher;

use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Scope;

/**
 * Identity and presence, for a value that has neither an order nor a string form.
 *
 * Returned by: Address, Boolean, Collection, CreditCard, Enum, File, Password.
 *
 * @see Matcher for why there are four of these rather than one, and why the verbs live in traits.
 */
final readonly class Basic implements Matcher
{
	use AsksAnything;

	public function __construct(public Scope $scope)
	{
	}
}
