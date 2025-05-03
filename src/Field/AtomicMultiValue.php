<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Property;
use InvalidArgumentException;

/**
 * @extends AtomicField<null|string|array>
 */
abstract class AtomicMultiValue extends AtomicField
{
	protected function process($value): Property\Value
	{
		if (is_string($value)) {
			return new Property\Value($this->parseValue($value));
		}

		if (is_array($value) || $value === null) {
			return new Property\Value($value);
		}

		throw new InvalidArgumentException('Value must be string, array, or null.');
	}

	abstract protected function parseValue(string $value): array;
}
