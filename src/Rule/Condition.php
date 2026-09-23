<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Facade;
use Meraki\Schema\Scope;

interface Condition
{
	/**
	 * @param array<string, mixed> $data what was submitted, under each field's name
	 */
	public function matches(array $data, Facade $schema): bool;

	/**
	 * @return array<Scope>
	 */
	public function getScopes(): array;
}
