<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Rule\Condition;

interface ConditionGroup extends Condition
{
	public function add(Condition $condition): static;
}
