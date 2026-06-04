<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;
use InvalidArgumentException;

/**
 * @phpstan-import-type SerializedField from Field
 * @phpstan-type SerializedPlaceholder = SerializedField&object{
 * 	type: 'placeholder'
 * }
 * @extends AtomicField<mixed|null, SerializedPlaceholder>
 */
final class Placeholder extends AtomicField
{
	public function __construct(
		Property\Name $name,
	) {
		parent::__construct($name);
	}

	public function validateValue(mixed $value): bool
	{
		return is_string($value);
	}

	protected function getConstraints(): array
	{
		return [];
	}
}
