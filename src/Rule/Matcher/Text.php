<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Matcher;

use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Scope;

/**
 * Identity, presence and text, for a value with a string form but no order.
 *
 * Returned by: EmailAddress, Name, PhoneNumber, Text, Uri, Uuid.
 *
 * @see Matcher for why there are four of these rather than one, and why the verbs live in traits.
 */
final readonly class Text implements Matcher
{
	use AsksAnything;
	use AsksText;

	public function __construct(public Scope $scope)
	{
	}
}
