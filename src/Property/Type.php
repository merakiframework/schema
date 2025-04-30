<?php
declare(strict_types=1);

namespace Meraki\Schema\Property;

use Meraki\Schema\Property;

final class Type implements Property
{
	public readonly string $name;

	public function __construct(public readonly string $value)
	{
		$this->name = 'type';
	}

	public function __toString(): string
	{
		return $this->value;
	}
}
