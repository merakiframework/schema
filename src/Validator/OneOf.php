<?php
declare(strict_types=1);

namespace Meraki\Schema\Validator;

use Meraki\Schema\Validator;
use Meraki\Schema\ValidatorName;
use Meraki\Schema\Field;

/**
 * @property-read ValidatorName $name
 */
final class OneOf implements Validator
{
	/** @param list<mixed> $value */
	public function __construct(public readonly array $value)
	{
		$this->name = new ValidatorName('one_of');
	}

	public function contains(mixed $value): bool
	{
		return in_array($value, $this->value, true);
	}

	public function validate(Field $field): bool
	{
		return $this->contains($field->value->unwrap());
	}
}
