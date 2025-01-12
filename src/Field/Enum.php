<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\Validator;

class Enum extends Field
{
	public function __construct(Attribute\Name $name, Attribute\OneOf $oneOf, Attribute ...$attributes)
	{
		parent::__construct(new Attribute\Type('enum'), $name, $oneOf, ...$attributes);

		$this->registerConstraints([
			Attribute\OneOf::class => self::getOneOfConstraintValidator()
		]);
	}

	public function oneOf(mixed ...$values): self
	{
		$this->attributes = $this->attributes->set(new Attribute\OneOf($values));

		return $this;
	}

	public static function getSupportedAttributes(): array
	{
		return [
			Attribute\OneOf::class,
		];
	}

	protected static function getOneOfConstraintValidator(): Validator
	{
		return new class() implements Validator {
			public function validate(Attribute&Constraint $constraint, Field $field): bool
			{
				return in_array($field->value, $constraint->value, true);
			}
		};
	}
}
