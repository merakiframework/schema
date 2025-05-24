<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Property;

final class Boolean extends AtomicField
{
	public function __construct(
		Property\Name $name,
		Property\Value $value = null,
		Property\Value $defaultValue = null,
		bool $optional = false,
	) {
		parent::__construct(new Property\Type('boolean', $this->validateType(...)), $name, $value, $defaultValue, $optional);
	}

	protected function cast(mixed $value): bool
	{
		return $value;
		// return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
		// 	?? throw new \InvalidArgumentException('Invalid boolean value: ' . $value);
	}

	protected function validateType(mixed $value): bool
	{
		return is_bool($value);
	}

	protected function getConstraints(): array
	{
		return [];
	}
}
