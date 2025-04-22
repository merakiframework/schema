<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Type;

use Meraki\Schema\Field\Type;
use Meraki\Schema\Validator\CheckType;

final class Boolean implements Type
{
	public string $name = 'boolean';

	public function accepts(mixed $value): bool
	{
		return is_bool($value);
	}

	public function getValidator(): CheckType
	{
		return new CheckType($this);
	}
}

// class Boolean extends Field
// {
// 	public function __construct(Attribute\Name $name, Attribute ...$attributes)
// 	{
// 		parent::__construct(new Attribute\Type('boolean'), $name, ...$attributes);
// 	}

// 	public static function getSupportedAttributes(): array
// 	{
// 		return Attribute\Set::ALLOW_ANY;
// 	}

// 	protected static function getTypeConstraintValidator(): Validator
// 	{
// 		return new class() implements Validator {
// 			public function validate(Attribute&Constraint $constraint, Field $field): bool
// 			{
// 				return $field->value === true || $field->value === false;
// 			}
// 		};
// 	}
// }
