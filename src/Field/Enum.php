<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;

/**
 * @template T of scalar
 * @phpstan-import-type SerializedField from Field
 * @phpstan-type SerializedEnum = SerializedField&object{
 * 	type: 'enum',
 * 	value: string|null,
 * 	oneOf: list<T>,
 * }
 * @extends AtomicField<string|null, SerializedEnum>
 */
final class Enum extends AtomicField
{
	public function __construct(
		Property\Name $name,
		/**
		 * @readonly
		 * @param list<T> $oneOf
		 */
		public array $oneOf,
	) {
		parent::__construct($name);
	}

	public function allow(mixed $value): self
	{
		if (!in_array($value, $this->oneOf, true)) {
			$this->oneOf[] = $value;
		}

		return $this;
	}

	public function validateValue(mixed $value): bool
	{
		return in_array($value, $this->oneOf, true);
	}

	protected function getConstraints(): array
	{
		return [];
	}
}
