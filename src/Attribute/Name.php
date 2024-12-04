<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;

/**
 * A "name" attribute.
 *
 * The name attribute is used to specify the name of a field.
 */
final class Name extends Attribute
{
	public string $prefix = '';

	public function __construct(string $value)
	{
		parent::__construct('name', $value);
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
}
