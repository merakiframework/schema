<?php
declare(strict_types=1);

namespace Meraki\Schema\Validator;

use Meraki\Schema\FieldType;
use Meraki\Schema\Validator;
use Meraki\Schema\ValidatorName;
use Meraki\Schema\Field;

/**
 * @property-read ValidatorName $name
 */
final class CheckType implements Validator
{
	public readonly ValidatorName $name;

	public function __construct(private readonly FieldType $type)
	{
		$this->name = new ValidatorName('type');
	}

	public function validate(Field $field): bool
	{
		return $this->type->accepts($field->value->unwrap());
	}
}
