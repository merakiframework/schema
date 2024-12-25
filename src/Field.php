<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\AggregatedValidationResults;
use Meraki\Schema\Attribute;
use Meraki\Schema\Validator;
use Meraki\Schema\ConstraintValidationResult;

/**
 * It is important to remember that a field is required by default. If you want
 * to make a field optional, you must add the `optional` attribute to it.
 */
class Field
{
	private array $validators = [];
	public Attribute\Set $attributes;

	public bool $inputGiven = false;

	public function __construct(
		public Attribute\Type $type,
		public Attribute\Name $name,
		Attribute ...$attributes,
	) {
		$this->attributes = new Attribute\Set(static::getSupportedAttributes(), $type, $name, ...$attributes);
		$this->attributes = $this->attributes->add(new Attribute\DefaultValue(null));

		if ($this->attributes->findByName('value') !== null) {
			$this->inputGiven = true;
		}

		$this->attributes = $this->attributes->add(new Attribute\Value(null));

		$this->updateValueWithDefaultValue();
	}

	protected function updateValueWithDefaultValue(): void
	{
		/** @var Attribute\Value */
		$value = $this->attributes->getByName('value');
		/** @var Attribute\DefaultValue */
		$defaultValue = $this->attributes->getByName('default_value');

		$value = $value->defaultsTo($defaultValue);

		// only update the value if no input was given and the field is optional
		if ($this->attributes->findByName('optional') !== null && $this->attributes->getByName('optional')->hasValueOf(true)) {
			$this->attributes = $this->attributes->set($value);
		}
	}

	public function addAttribute(Attribute $attributes): static
	{
		$this->attributes = $this->attributes->add($attributes);

		return $this;
	}

	public function setAttribute(Attribute $attribute): static
	{
		$this->attributes = $this->attributes->set($attribute);

		return $this;
	}

	/**
	 * @param array<class-string, Validator> $constraints
	 */
	public function registerConstraints(array $constraints): static
	{
		foreach ($constraints as $fqcn => $validator) {
			$this->registerConstraint($fqcn, $validator);
		}

		return $this;
	}

	/**
	 * @param class-string $fqcn
	 */
	public function registerConstraint(string $fqcn, Validator $validator): static
	{
		$this->validators[$fqcn] = $validator;

		return $this;
	}

	public function hasNameOf(Attribute|string $name): bool
	{
		if (is_string($name)) {
			$name = new Attribute('name', $name);
		}

		return $this->name->equals($name);
	}

	public function require(): static
	{
		$this->attributes = $this->attributes->set(new Attribute\Optional(false));

		return $this;
	}

	public function makeOptional(): static
	{
		$this->attributes = $this->attributes->set(new Attribute\Optional(true));

		return $this;
	}

	public function constrain(Attribute&Constraint $attribute): static
	{
		$this->attributes = $this->attributes->set($attribute);

		return $this;
	}

	public function input(mixed $value): static
	{
		$this->inputGiven = true;
		$this->attributes = $this->attributes->set(new Attribute\Value($value));

		$this->updateValueWithDefaultValue();

		return $this;
	}

	public function prefill(mixed $value): static
	{
		$this->attributes = $this->attributes->set(new Attribute\DefaultValue($value));

		$this->updateValueWithDefaultValue();

		return $this;
	}

	public function isRequired(): bool
	{
		return !$this->isOptional();
	}

	public function isOptional(): bool
	{
		$optional = $this->attributes->findByName('optional');

		return $optional !== null && $optional->hasValueOf(true);
	}

	/**
 	 * Check whether a field was given any input.
   	 */
	protected function valueNotGiven(Attribute\Value $value): bool
	{
		return $value->hasValueOf(null);
	}

	public function validate(): FieldValidationResult
	{
		/** @var Attribute\Optional|null */
		$optional = $this->attributes->findByName('optional');
		/** @var Attribute\Value */
		$value = $this->attributes->getByName('value');
		/** @var Attribute\DefaultValue */
		$defaultValue = $this->attributes->getByName('default_value');

		$isOptional = $optional !== null && $optional->hasValueOf(true);

		if ($isOptional) {
			$value = $value->defaultsTo($defaultValue);

			// If optional, no value, no default value, then skip all validation.
			if ($this->valueNotGiven($value)) {
				return new FieldValidationResult(
					$this,
					FieldValueValidationResult::skip($value),
					$this->skipAllConstraints(),
				);
			}
		}

		$valueResult = $this->validateValue($value);

		if ($valueResult->failed()) {
			return new FieldValidationResult($this, $valueResult, $this->skipAllConstraints());
		}

		return new FieldValidationResult($this, $valueResult, $this->validateConstraints());
	}

	private function skipAllConstraints(): AggregatedConstraintValidationResults
	{
		$results = new AggregatedConstraintValidationResults();

		foreach ($this->attributes->getConstraints() as $constraint) {
			$results = $results->add(ConstraintValidationResult::skip($constraint));
		}

		return $results;
	}

	protected function validateValue(Attribute\Value $value): FieldValueValidationResult
	{
		if ($value->value !== null && $this->isCorrectType($value->value)) {
			return FieldValueValidationResult::pass($value);
		}

		// no value or incorrect type
		return FieldValueValidationResult::fail($value);
	}

	protected function isCorrectType(mixed $value): bool
	{
		return true;
	}

	protected function validateConstraints(): AggregatedConstraintValidationResults
	{
		$results = new AggregatedConstraintValidationResults();
		$constraints = $this->attributes->getConstraints();

		foreach ($constraints as $constraint) {
			$validator = $this->validators[$constraint::class];

			if ($validator === null) {
				throw new \RuntimeException("No validator found for constraint '{$constraint->name}'.");
			}

			$constraintResult = ConstraintValidationResult::guess($validator->validate($constraint, $this), $constraint);
			$results = $results->add($constraintResult);
		}

		return $results;
	}

	protected static function getValidatorForValue(): Validator
	{
		return new class() implements Validator {
			public function validate(Attribute&Constraint $constraint, Field $field): bool
			{
				return true;
			}
		};
	}

	public function __isset($name): bool
	{
		return $this->attributes->findByName($name) !== null;
	}

	public function __get($name): mixed
	{
		if ($attr = $this->attributes->findByName($name)) {
			return $attr->value;
		}

		return null;
	}

	public static function getSupportedAttributes(): array
	{
		return Attribute\Set::ALLOW_ANY;
	}
}
