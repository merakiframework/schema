<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\Validator;

class Boolean extends Field
{
	public function __construct(Attribute\Name $name, Attribute ...$attributes)
	{
		parent::__construct(new Attribute\Type('boolean'), $name, ...$attributes);
	}

	public static function getSupportedAttributes(): array
	{
		return Attribute\Set::ALLOW_ANY;
	}

	protected function isCorrectType(mixed $value): bool
	{
		return is_bool($value)
			|| (is_string($value) && (strcasecmp($value, 'on') === 0 || strcasecmp($value, 'off') === 0));
	}
}
