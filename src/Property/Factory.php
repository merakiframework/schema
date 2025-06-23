<?php
declare(strict_types=1);

namespace Meraki\Schema\Property;

use Meraki\Schema\Property;

final class Factory
{
	public function createName(string $value): Property\Name
	{
		return new Property\Name($value);
	}

	/**
	 * @param callable(string): bool $validator
	 */
	public function createType(string $value, callable $validator): Property\Type
	{
		return new Property\Type($value, $validator);
	}

	public function createValue(mixed $value): Property\Value
	{
		return new Property\Value($value);
	}
}
