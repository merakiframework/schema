<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

/**
 * A "name" attribute.
 *
 * The name attribute is used to specify the name of a field.
 */
final class Name
{
	public string $name = 'name';
	public string $prefix = '';

	public function __construct(public readonly string $value)
	{
		// parent::__construct('name', $value);
	}

	public function prefixWith(string $prefix): self
	{
		$this->removePrefix();

		$this->prefix = $prefix;
		$this->value = $this->prefix . $this->value;

		return $this;
	}

	public function removePrefix(): self
	{
		$this->value = substr($this->value, strlen($this->prefix));
		$this->prefix = '';

		return $this;
	}

	public function __toString(): string
	{
		return $this->value;
	}
}
