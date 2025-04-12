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
	public function __construct(mixed $value)
	{
		parent::__construct('value', $value);
	}

	public static function of(mixed $value): self
	{
		return new self($value);
	}

	public function defaultsTo(Attribute\DefaultValue $value): self
	{
		$copy = clone $this;

		if ($copy->value === null) {
			$copy->value = $value->value;
		}

		return $copy;
	}
}
