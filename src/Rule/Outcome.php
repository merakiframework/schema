<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Facade;
use Meraki\Schema\Scope;

interface Outcome
{
	public function apply(Facade $schema): void;

	public function getScope(): Scope;
}
