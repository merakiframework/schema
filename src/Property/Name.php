<?php
declare(strict_types=1);

namespace Meraki\Schema\Property;

use Meraki\Schema\Property;

/**
 * A "name" attribute.
 *
 * The name attribute is used to specify the name of a field.
 */
final class Name implements Property
{
	public const PREFIX_SEPARATOR = '.';
	public readonly string $name;
	public string $prefix = '';

	public function __construct(public readonly string $value)
	{
		$this->name = 'name';
		// parent::__construct('name', $value);
	}

	public function prefixWith(string|self $prefix): self
	{
		if ($prefix instanceof self) {
			$prefix = $prefix->value;
		}

		$self = new self($prefix . self::PREFIX_SEPARATOR . $this->value);
		$self->prefix = $prefix;

		return $self;
	}

	public function removePrefix(): self
	{
		if ($this->prefix === '') {
			return $this;
		}

		$self = new self(substr($this->value, strlen($this->prefix . self::PREFIX_SEPARATOR)));
		$self->prefix = '';

		return $self;
	}

	public function __toString(): string
	{
		return $this->value;
	}
}
