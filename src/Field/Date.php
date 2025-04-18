<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\Validator;
use Brick\DateTime\LocalDate;
use Brick\DateTime\Period;

class Date extends Field
{
	public function __construct(Attribute\Name $name, Attribute ...$attributes)
	{
		parent::__construct(new Attribute\Type('date'), $name, ...$attributes);

		$this->registerConstraints([
			Attribute\Max::class => static::getMaxConstraintValidator(),
			Attribute\Min::class => static::getMinConstraintValidator(),
			Attribute\Step::class => static::getStepConstraintValidator(),
		]);
	}

	/**
	 * This is inclusive of the date provided.
	 */
	public function from(string $date): self
	{
		return $this->constrain(new Attribute\Min((string) LocalDate::parse($date)));
	}

	/**
	 * This is exclusive of the date provided.
	 */
	public function until(string $date): self
	{
		return $this->constrain(new Attribute\Max((string) LocalDate::parse($date)));
	}

	public function inMultiplesOf(string $date): self
	{
		return $this->constrain(new Attribute\Step((string) Period::parse($date)));
	}

	public static function getSupportedAttributes(): array
	{
		return [
			Attribute\Max::class,
			Attribute\Min::class,
			Attribute\Step::class,
		];
	}

	protected static function getTypeConstraintValidator(): Validator
	{
		return new class() implements Validator {
			public function validate(Attribute&Constraint $constraint, Field $field): bool
			{
				if ($field->value === null || $field->value === '') {
					return false;
				}

				try {
					LocalDate::parse($field->value);
					return true;
				} catch (\Exception $e) {
					return false;
				}
			}
		};
	}

	protected static function getMaxConstraintValidator(): Validator
	{
		return new class() implements Validator {
			public function validate(Attribute&Constraint $constraint, Field $field): bool
			{
				$max = LocalDate::parse($constraint->value);

				return LocalDate::parse($field->value)->isBefore($max);
			}
		};
	}

	protected static function getMinConstraintValidator(): Validator
	{
		return new class() implements Validator {
			public function validate(Attribute&Constraint $constraint, Field $field): bool
			{
				$min = LocalDate::parse($constraint->value);

				return LocalDate::parse($field->value)->isAfterOrEqualTo($min);
			}
		};
	}

	protected static function getStepConstraintValidator(): Validator
	{
		return new class() implements Validator {
			public function validate(Attribute&Constraint $constraint, Field $field): bool
			{
				$min = $field->attributes->findByName(Attribute\Min::class);

				// the step constraint must be relative to a minimum date
				// so check for min constraint
				if ($min === null) {
					return false;
				}

				$min = LocalDate::parse($min->value);
				$step = Period::parse($constraint->value);
				$value = LocalDate::parse($field->value);
				$period = Period::between($min, $value);

				return $period->isEqualTo($step);
			}
		};
	}
}
