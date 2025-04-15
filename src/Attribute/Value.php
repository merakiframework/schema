<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;

/**
 * A "value" attribute.
 *
 * The value attribute is used to specify the value of a field.
 */
class Value extends Attribute
{
	/** @readonly */
	public mixed $defaultValue;

	/** @readonly */
	public bool $isUsingDefaultValue = false;

	/** @readonly */
	public bool $resolved = false;

	public function __construct(mixed $value, mixed $defaultValue = null)
	{
		parent::__construct('value', $value);

		$this->defaultValue = $defaultValue;
	}

	public static function of(mixed $value, mixed $defaultValue = null): self
	{
		return new self($value, $defaultValue);
	}

	public function defaultsTo(mixed $value): self
	{
		return new self($this->value, $value);
	}

	public function resolve(): Attribute\Value
	{
		if ($this->resolved) {
			return $this;
		}

		if ($this->value === null) {
			$instance = new self($this->defaultValue, $this->defaultValue);
			$instance->isUsingDefaultValue = true;
			$instance->resolved = true;

			return $instance;
		}

		$instance = new self($this->value, $defaultValue ?? $this->defaultValue);
		$instance->isUsingDefaultValue = false;
		$instance->resolved = true;

		return $instance;
	}
}
