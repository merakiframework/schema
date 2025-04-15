<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\FieldValidationResult;
use Meraki\Schema\Attribute;
use Meraki\Schema\Validator;
use Meraki\Schema\ConstraintValidationResult;

/**
 * It is important to remember that a field is required by default. If you want
 * to make a field optional, you must add the `optional` attribute to it.
 *
 * @property-read Attribute\Type $type
 * @property-read Attribute\Name $name
 * @property-read Attribute\Value $value
 */
class Field
{
	protected array $validators = [];
	public Attribute\Set $attributes;

	public bool $inputGiven = false;

	public FieldValidationResult $validationResult;

	protected bool $deferValidation = false;

	public function __construct(
		public Attribute\Type $type,
		public Attribute\Name $name,
		Attribute ...$attributes,
	) {
		$this->attributes = new Attribute\Set(static::getSupportedAttributes(), $type, $name, ...$attributes);
		$defaultValue = $this->attributes->findByName(Attribute\DefaultValue::class);
		$value = $this->attributes->findByName(Attribute\Value::class) ?? Attribute\Value::of(null, null);

		if ($defaultValue !== null) {
			$value = $value->defaultsTo($defaultValue->value);
		}

		$this->registerConstraint(Attribute\Type::class, static::getTypeConstraintValidator());

		// if a value attribute is given then input was given
		if ($value !== null) {
			$this->prefill($value->defaultValue);
			$this->input($value->value);
		}

		$this->validationResult = new FieldValidationResult();
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

	public function deferValidation(): static
	{
		$this->deferValidation = true;

		return $this;
	}

	public function input(mixed $value): static
	{
		$this->inputGiven = true;
		$originalValue = $this->attributes->getByName(Attribute\Value::class);
		$this->attributes = $this->attributes->set(
			Attribute\Value::of($value, $originalValue->defaultValue)->resolve()
		);

		if (!$this->deferValidation) {
			$this->validate();
		}

		return $this;
	}

	public function prefill(mixed $defaultValue): static
	{
		$value = $this->attributes->getByName(Attribute\Value::class);
		$this->attributes = $this->attributes->set($value->defaultsTo($defaultValue));

		return $this;
	}

	public function isRequired(): bool
	{
		return !$this->isOptional();
	}

	public function isOptional(): bool
	{
		$optional = $this->attributes->findByName(Attribute\Optional::class);

		return $optional !== null && $optional->hasValueOf(true);
	}

	/**
 	 * Check whether a field was given any input.
	 *
	 * This value has been 'resolved' with either the input value or the default value.
	 */
	protected function valueNotGiven(Attribute\Value $value): bool
	{
		return $value->hasValueOf(null);
	}

	public function validate(): FieldValidationResult
	{
		$value = $this->attributes->getByName(Attribute\Value::class)->resolve();
		$this->attributes = $this->attributes->set($value);

		// If optional, no value and no default value, then skip all validation.
		if ($this->isOptional() && $this->valueNotGiven($value)) {
			$results = new FieldValidationResult();

			foreach ($this->attributes->getConstraints() as $constraint) {
				$results = $results->add(ConstraintValidationResult::skip($constraint));
			}

			$this->validationResult = $results;

			return $this->validationResult;
		}

		$this->validateConstraints();

		return $this->validationResult;
	}

	private function skipAllConstraints(?Attribute\Set $constraints = null): void
	{
		$results = new FieldValidationResult();

		foreach (($constraints ?: $this->attributes->getConstraints()) as $constraint) {
			$results = $results->add(ConstraintValidationResult::skip($constraint));
		}

		$this->validationResult = $results;
	}

	protected static function getTypeConstraintValidator(): Validator
	{
		return new class() implements Validator {
			public function validate(Attribute&Constraint $constraint, Field $field): bool
			{
				return true;
			}
		};
	}

	protected function validateConstraints(): void
	{
		$results = new FieldValidationResult();
		$constraints = $this->attributes->getConstraints();

		// validate type constraint first
		$typeConstraint = $constraints->getByName(Attribute\Type::class);

		$this->assertValidatorExistsForConstraint($typeConstraint);

		$validatorForTypeConstraint = $this->validators[$typeConstraint::class];
		$typeConstraintResult = ConstraintValidationResult::guess(
			$validatorForTypeConstraint->validate($typeConstraint, $this),
			$typeConstraint
		);
		$results = $results->add($typeConstraintResult);

		// filter out type constraint
		$constraints = $constraints->filter(fn($constraint): bool => $constraint !== $typeConstraint);

		// if type constraint failed, skip validation of other constraints
		if ($typeConstraintResult->failed()) {
			foreach ($constraints as $constraint) {
				$results = $results->add(ConstraintValidationResult::skip($constraint));
			}

			$this->validationResult = $results;
			return;
		}

		// validate other constraints
		foreach ($constraints as $constraint) {
			$this->assertValidatorExistsForConstraint($constraint);

			$validator = $this->validators[$constraint::class];

			$constraintResult = ConstraintValidationResult::guess($validator->validate($constraint, $this), $constraint);
			$results = $results->add($constraintResult);
		}

		$this->validationResult = $results;
	}

	private function assertValidatorExistsForConstraint(Attribute&Constraint $constraint): void
	{
		if (!array_key_exists($constraint::class, $this->validators)) {
			$fqcn = $constraint::class;
			throw new \RuntimeException("No validator found for constraint '{$fqcn}'.");
		}
	}

	/**
	 * @template T of Attribute
	 * @param class-string<T> $name Fully qualified class name of the attribute
	 */
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
