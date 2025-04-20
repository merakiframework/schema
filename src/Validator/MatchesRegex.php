<?php
declare(strict_types=1);

namespace Meraki\Schema\Validator;

use Meraki\Schema\Field;
use Meraki\Schema\Validator;
use Meraki\Schema\ValidatorName;

/**
 * @property-read ValidatorName $name
 */
final class MatchesRegex implements Validator
{
	public readonly ValidatorName $name;

	public function __construct(public readonly string $value)
	{
		$this->name = new ValidatorName('pattern');
	}

	public function validate(Field $field): bool
	{
		return is_string($field->value->unwrap()) && preg_match($field->value->unwrap(), $this->value) === 1;
	}
}
