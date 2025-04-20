<?php
declare(strict_types=1);

namespace Meraki\Schema\Validator;

use Meraki\Schema\Validator;
use Meraki\Schema\ValidatorName;
use Meraki\Schema\Field;

/**
 * @property-read ValidatorName $name
 */
final class HasMaxCharCountOf implements Validator
{
	public readonly ValidatorName $name;

	public function __construct(public readonly int $value)
	{
		$this->name = new ValidatorName('max');
	}

	public function validate(Field $field): bool
	{
		return mb_strlen((string)$field->value->unwrap()) <= $this->value;
	}
}
