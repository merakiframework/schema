<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Property;

/**
 * @template T of mixed
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
		Property\Value $value = null,
		Property\Value $defaultValue = null,
		bool $optional = false,
	) {
		parent::__construct(new Property\Type('enum'), $name, $value, $defaultValue, $optional);
	}

	public function allow(mixed $value): self
	{
		if (!in_array($value, $this->oneOf, true)) {
			$this->oneOf[] = $value;
		}

		return $this;
	}

	protected function validateType(mixed $value): bool
	{
		return in_array($value, $this->oneOf, true);
	}

	protected function getConstraints(): array
	{
		return [];
	}
}
