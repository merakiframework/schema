<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;

/**
 * @extends AtomicField<bool|null>
 */
final class Boolean extends AtomicField
{
	public private(set) bool $mustBeAccepted = false;

	public function __construct(
		Property\Name $name,
	) {
		parent::__construct($name);
	}

	/**
	 * Requires the field to be present and `true` (e.g. an "I agree to the terms"
	 * checkbox). Makes the field required and adds an `accepted` constraint that
	 * fails on `false`.
	 */
	public function mustBeAccepted(): self
	{
		$this->mustBeAccepted = true;
		$this->require();

		return $this;
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
		if (!$this->mustBeAccepted) {
			return [];
		}

		return [
			'accepted' => fn(mixed $value): ?bool => is_bool($value) ? $value === true : null,
		];
	}
}
