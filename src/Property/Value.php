<?php
declare(strict_types=1);

namespace Meraki\Schema\Property;

use Meraki\Schema\Property;

/**
 * A field's "value" property.
 *
 * The value attribute is used to specify the value or default_value of a field.
 */
final class Value implements Property
{
	public readonly string $name;

	public function __construct(public readonly mixed $value)
	{
		$this->name = 'value';
	}

	public static function of(mixed $value): self
	{
		return new self($value);
	}

	public function unwrap(): mixed
	{
		return $this->value;
	}

	public function notProvided(): bool
	{
		return $this->value === null;
	}

	public function provided(): bool
	{
		return $this->value !== null;
	}
}
