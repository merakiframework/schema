<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Property;

/**
 * @template T of scalar
 * @extends Field<string|null>
 */
final class Enum extends Field
{
	public function __construct(
		public readonly Property\Name $name,
		/**
		 * @param list<T> $oneOf
		 */
		public array $oneOf,
	) {
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
