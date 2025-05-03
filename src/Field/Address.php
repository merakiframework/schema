<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Composite as CompositeField;
use Meraki\Schema\Property;

final class Address extends CompositeField
{
	public function __construct(
		Property\Name $name,
		Property\Value $value = null,
		Property\Value $defaultValue = null,
		bool $optional = false,
	) {
		parent::__construct(new Property\Type('address'), $name, $value, $defaultValue, $optional);
	}

	protected function cast(mixed $value): mixed
	{
		return $value;
	}

	protected function validateType(mixed $value): bool
	{
		return is_string($value);
	}

	protected function getConstraints(): array
	{
		return [];
	}
}
