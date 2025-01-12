<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\Validator;

class Passphrase extends Field
{
	public function __construct(Attribute\Name $name, Attribute ...$attributes)
	{
		parent::__construct(new Attribute\Type('passphrase'), $name, ...$attributes);
	}

	public static function getSupportedAttributes(): array
	{
		return Attribute\Set::ALLOW_ANY;
	}

	protected static function getTypeConstraintValidator(): Validator
	{
		return new class() implements Validator {
			public function validate(Attribute&Constraint $constraint, Field $field): bool
			{
				return is_string($field->value);
			}
		};
	}
}
