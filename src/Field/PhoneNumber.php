<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\Validator;

/**
 * A "phone number" field is used to represent an international or national phone number.
 *
 * It conforms to the E.164 format for international phone numbers:
 *  - starts with a '+' followed by the country code and the subscriber number.
 *  - can include spaces, dashes, periods, and parentheses for formatting.
 *  - cannot contain any other characters.
 *  - must be between 2 and 15 digits long.
 */
class PhoneNumber extends Field
{
	private const TYPE_PATTERN = '/^\+[ ]?(?:\d[ ]?){2,15}$/';
	private const CHARS_TO_REMOVE = [' ', '-', '.', '(', ')'];

	public function __construct(Attribute\Name $name, Attribute ...$attributes)
	{
		parent::__construct(new Attribute\Type('phone_number'), $name, ...$attributes);
	}

	public static function getSupportedAttributes(): array
	{
		return Attribute\Set::ALLOW_ALWAYS_SUPPORTED_ONLY;
	}

	protected static function getTypeConstraintValidator(): Validator
	{
		return new class(self::TYPE_PATTERN) implements Validator {
			public function __construct(private string $pattern) {}
			public function validate(Attribute&Constraint $constraint, Field $field): bool
			{
				$value = $field->value;

				return is_string($value) && preg_match($this->pattern, $value) === 1;
			}
		};
	}
}
