<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;

/**
 * A "version" attribute.
 *
 * A version attribute can be used to restrict the value of a field to a specific
 * version or range of versions. An empty array indicates that the field is not
 * restricted to any version.
 *
 * @property-read list<int> $value
 */
final class Version extends Attribute implements Constraint
{
	public function __construct(array $values = [])
	{
		$this->assertAllSameType($values);

		parent::__construct('version', $this->removeDuplicates($values));
	}

	public function add(mixed $value, mixed ...$values): void
	{
		$this->value = $this->removeDuplicates(array_merge($this->value, [$value], $values));
	}

	public function isAllowed(mixed $value): bool
	{
		return count($this->value) === 0 || in_array($value, $this->value, true);
	}

	private function removeDuplicates(array $values): array
	{
		return array_values(array_unique($values));
	}

	private function assertAllSameType(array $values): void
	{
		if (count($values) === 0) {
			return;
		}

		$type = gettype($values[0]);

		foreach ($values as $index => $value) {
			if (gettype($value) !== $type) {
				throw new \InvalidArgumentException('All values must be of the same type: expected `'.$type.'`, got `'.gettype($value) . '` at position '.$index);
			}
		}
	}
}
