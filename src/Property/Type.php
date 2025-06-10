<?php
declare(strict_types=1);

namespace Meraki\Schema\Property;

use Meraki\Schema\Property;

final class Type extends Property
{
	/** @var callable(mixed): ?bool $validator */
	public readonly mixed $validator;

	/** @param callable(mixed): ?bool $validator */
	public function __construct(string $value, callable $validator)
	{
		parent::__construct('type', $value);

		if (!is_callable($validator)) {
			throw new \TypeError('Validator must be a callable.');
		}

		$this->validator = $validator;
	}

	public function __toString(): string
	{
		return $this->value;
	}
}
