<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Matcher;

use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Scope;

/**
 * Every question there is, for a value that can be both ranked and read as text.
 *
 * Returned by: Number, Date, DateTime, Time, Duration — and Facade::when(), where the field is not known at authoring time.
 *
 * @see Matcher for why there are four of these rather than one, and why the verbs live in traits.
 */
final readonly class OrderedText implements Matcher
{
	use AsksAnything;
	use AsksOrder;
	use AsksText;
	public function __construct(public Scope $scope)
	{
	}
}
