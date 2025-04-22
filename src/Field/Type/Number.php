<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Type;

use Meraki\Schema\Field\Type;
use Meraki\Schema\Validator\CheckType;

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
final class Number implements Type
{
	public string $name = 'number';

	public function accepts(mixed $value): bool
	{
		return is_int($value) || is_float($value);
	}

	public function getValidator(): CheckType
	{
		return new CheckType($this);
	}
}


// class Number extends Field
// {
// 	public function __construct(Attribute\Name $name, Attribute ...$attributes)
// 	{
// 		$this->registerConstraints([
// 			Attribute\Min::class => new Validator\CheckMinValue(),
// 			Attribute\Max::class => new Validator\CheckMaxValue(),
// 		]);
// 	}

// 	public function minOf(int $minValue): self
// 	{
// 		return $this->constrain(new Attribute\Min($minValue));
// 	}

// 	public function maxOf(int $maxValue): self
// 	{
// 		return $this->constrain(new Attribute\Max($maxValue));
// 	}

// 	public function inIncrementsOf(float $step): self
// 	{
// 		$this->attributes = $this->attributes->set(new Attribute\Step($step));

// 		return $this;
// 	}

// 	public static function getSupportedAttributes(): array
// 	{
// 		return [
// 			Attribute\Min::class,
// 			Attribute\Max::class,
// 			Attribute\Step::class,
// 		];
// 	}
// }
