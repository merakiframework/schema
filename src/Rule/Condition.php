<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Facade;
use Meraki\Schema\Scope;

interface Condition
{
	public function matches(array $data, Facade $schema): bool;

	/**
	 * @return array<Scope>
	 */
	public function getScopes(): array;
}
