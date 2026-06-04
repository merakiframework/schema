<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;

/**
 * @phpstan-import-type SerializedField from Field
 * @phpstan-type SerializedBoolean = SerializedField&object{
 * 	type: 'boolean',
 * 	value: bool|null
 * }
 * @extends AtomicField<bool|null, SerializedBoolean>
 */
final class Boolean extends AtomicField
{
	public function __construct(
		Property\Name $name,
	) {
		parent::__construct($name);
	}

	protected function cast(mixed $value): bool
	{
		return $value;
		// return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
		// 	?? throw new \InvalidArgumentException('Invalid boolean value: ' . $value);
	}

	public function validateValue(mixed $value): bool
	{
		return is_bool($value);
	}

	protected function getConstraints(): array
	{
		return [];
	}
}
