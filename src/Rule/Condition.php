<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Field;
use Meraki\Schema\Scope;

interface Condition
{
	/**
	 * @param array<string, mixed> $data what was submitted, under each field's name
	 */
	public function matches(array $data, Field\Set $fields): bool;

	/**
	 * @return list<Scope>
	 */
	public function getScopes(): array;
}
