<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Matcher;

use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Rule\Quantifier;
use Meraki\Schema\Scope;

/**
 * Identity and presence, for a value that has neither an order nor a string form.
 *
 * Returned by: Address, Boolean, Collection, CreditCard, File, Password.
 *
 * @see Matcher for why there are four of these rather than one, and why the verbs live in traits.
 */
final readonly class Basic implements Matcher
{
	use BuildsDrafts;
	use AsksAnything;

	public function __construct(
		public Scope $scope,
		/** Set when this asks about every row of a collection rather than about one value. */
		public ?Quantifier $quantifier = null,
	) {
	}
}
