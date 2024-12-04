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
	private const TYPE_PATTERN = '/^\+(\d{1,3})[\s\-().]*((\d[\s\-().]*){7,14})$/';

	public function __construct(Attribute\Name $name, Attribute ...$attributes)
	{
		parent::__construct(new Attribute\Type('phone_number'), $name, ...$attributes);
	}

	public static function getSupportedAttributes(): array
	{
		return Attribute\Set::ALLOW_ALWAYS_SUPPORTED_ONLY;
	}

	protected function isCorrectType(mixed $value): bool
	{
		return is_string($value) && preg_match(self::TYPE_PATTERN, $value) === 1;
	}
}
