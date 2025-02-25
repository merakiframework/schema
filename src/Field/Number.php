<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\Validator;

/**
 * Represents a number input field.
 *
 * A number field can be an integer, float, or scientific notation.
 *
 * DO NOT use a number field for telephone numbers, postal codes,
 * dates, money, etc... Use (or create your own) specialized field
 * for those types of data. Failing that, use a text field with the
 * appropriate constraint attributes set.
 *
 * Use the constraint attributes to set the number type if you need
 * to restrict the type of number allowed. For example:
 *	- to only allow integers, set the 'step' attribute to 1
 *	- to only allow positive numbers, set the 'min' attribute to 0
 *	- to only allow negative numbers, set the 'max' attribute to 0
 *	- to force floats, set the step attribute to a decimal value (e.g. 0.1)
 */
class Number extends Field
{
	public function __construct(Attribute\Name $name, Attribute ...$attributes)
	{
		parent::__construct(new Attribute\Type('number'), $name, ...$attributes);

		$this->registerConstraints([
			Attribute\Min::class => new Validator\CheckMinValue(),
			Attribute\Max::class => new Validator\CheckMaxValue(),
		]);
	}

	public function minOf(int $minValue): self
	{
		return $this->constrain(new Attribute\Min($minValue));
	}

	public function maxOf(int $maxValue): self
	{
		return $this->constrain(new Attribute\Max($maxValue));
	}

	public function inIncrementsOf(int $step): self
	{
		$this->attributes = $this->attributes->set(new Attribute\Step($step));

		return $this;
	}

	public static function getSupportedAttributes(): array
	{
		return [
			Attribute\Min::class,
			Attribute\Max::class,
			Attribute\Step::class,
		];
	}

	protected static function getTypeConstraintValidator(): Validator
	{
		return new class() implements Validator {
			public function validate(Attribute&Constraint $constraint, Field $field): bool
			{
				return is_integer($field->value) || is_float($field->value);
			}
		};
	}
}
