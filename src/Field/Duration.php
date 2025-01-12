<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\Validator;
use Brick\DateTime;

class Duration extends Field
{
	private const TYPE_PATTERN = '/^P(?:\d+Y)?(?:\d+M)?(?:\d+D)?(?:T(?:\d+H)?(?:\d+M)?(?:\d+S)?)?$/';

	public function __construct(Attribute\Name $name, Attribute ...$attributes)
	{
		parent::__construct(new Attribute\Type('duration'), $name, ...$attributes);

		$this->registerConstraint(Attribute\Min::class, $this->getMinConstraintValidator());
		$this->registerConstraint(Attribute\Max::class, $this->getMaxConstraintValidator());
		$this->registerConstraint(Attribute\Step::class, $this->getStepConstraintValidator());
	}

	public function minOf(string $value): self
	{
		$this->attributes = $this->attributes->set(new Attribute\Min($value));

		return $this;
	}

	public function maxOf(string $value): self
	{
		$this->attributes = $this->attributes->set(new Attribute\Max($value));

		return $this;
	}

	public function inIncrementsOf(string $value): self
	{
		$this->attributes = $this->attributes->set(new Attribute\Step($value));

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
				$value = $field->value;

				if (!is_string($value)) {
					return false;
				}

				try {
					DateTime\Duration::parse($value);

					return true;
				} catch (\Exception $e) {
					return false;
				}
			}
		};
	}

	private function getMinConstraintValidator(): Validator
	{
		return new class() implements Validator {
			public function validate(Attribute&Constraint $attribute, Field $field): bool
			{
				$expectedMin = DateTime\Duration::parse($attribute->value);
				$actual = DateTime\Duration::parse($field->value);

				return $actual->isGreaterThanOrEqualTo($expectedMin);
			}
		};
	}

	private function getMaxConstraintValidator(): Validator
	{
		return new class() implements Validator {
			public function validate(Attribute&Constraint $attribute, Field $field): bool
			{
				$expectedMax = DateTime\Duration::parse($attribute->value);
				$actual = DateTime\Duration::parse($field->value);

				return $actual->isLessThanOrEqualTo($expectedMax);
			}
		};
	}

	private function getStepConstraintValidator(): Validator
	{
		return new class() implements Validator {
			public function validate(Attribute&Constraint $attribute, Field $field): bool
			{
				$step = DateTime\Duration::parse($attribute->value);
				$actual = DateTime\Duration::parse($field->value);

				return $actual->toSeconds() % $step->toSeconds() === 0;
			}
		};
	}
}
