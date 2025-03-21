<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Brick\DateTime\Duration;
use Brick\DateTime\LocalDateTime;
use Brick\DateTime\TimeZone;
use Meraki\Schema\Field;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\Validator;

class DateTime extends Field
{
	public function __construct(Attribute\Name $name, Attribute ...$attributes)
	{
		parent::__construct(new Attribute\Type('date_time'), $name, ...$attributes);

		$this->registerConstraints([
			Attribute\Max::class => static::getMaxConstraintValidator(),
			Attribute\Min::class => static::getMinConstraintValidator(),
			Attribute\Step::class => static::getStepConstraintValidator(),
		]);
	}

	/**
	 * This is inclusive of the date-time provided.
	 */
	public function from(string $dateTime): self
	{
		return $this->constrain(new Attribute\Min((string) LocalDateTime::parse($dateTime)));
	}

	/**
	 * This is exclusive of the date-time provided.
	 */
	public function until(string $dateTime): self
	{
		return $this->constrain(new Attribute\Max((string) LocalDateTime::parse($dateTime)));
	}

	public function inIncrementsOf(string $duration): self
	{
		return $this->constrain(new Attribute\Step((string) Duration::parse($duration)));
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
					LocalDateTime::parse($field->value);
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
				return LocalDateTime::parse($field->value)
					->isBefore(LocalDateTime::parse($constraint->value));
			}
		};
	}

	protected static function getMinConstraintValidator(): Validator
	{
		return new class() implements Validator {
			public function validate(Attribute&Constraint $constraint, Field $field): bool
			{
				return LocalDateTime::parse($field->value)
					->isAfterOrEqualTo(LocalDateTime::parse($constraint->value));
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

				$min = LocalDateTime::parse($min->value)->withSecond(0)->withNano(0);
				$step = Duration::parse($constraint->value);
				$value = LocalDateTime::parse($field->value)->withSecond(0)->withNano(0);
				$duration = Duration::between(
					$min->atTimeZone(TimeZone::utc())->getInstant(),
					$value->atTimeZone(TimeZone::utc())->getInstant()
				);

				return $duration->compareTo($step) === 0;
			}
		};
	}
}
