<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Validator;
use RuntimeException;

class Field
{
	// /** @var list<Validator> $validators */
	public array $validators = [];

	public Field\Value $value;

	public bool $inputGiven;

	public bool $optional;

	public Field\Value $defaultValue;

	public function __construct(
		public readonly Field\Type $type,
		public readonly Field\Name $name,
		Field\Value $value = null,
		Field\Value $defaultValue = null,
		bool $optional = false,
	) {
		$this->defaultValue = $defaultValue ?? new Field\Value(null);
		$this->value = $value ?? new Field\Value(null);
		$this->inputGiven = $value !== null;
		$this->validators = [$type->getValidator()];
		$this->optional = $optional;
	}

	public function addValidator(Validator $validator): static
	{
		if (!$this->supportsValidator($validator)) {
			throw new RuntimeException("Validator '{$validator->name}' is not supported by this field.");
		}

		// update existing validator in place
		foreach ($this->validators as $index => $existingValidator) {
			if ($existingValidator->name->equals($validator->name)) {
				$this->validators[$index] = $validator;
				return $this;
			}
		}

		// add new validator
		$this->validators[] = $validator;

		return $this;
	}

	public function supportsValidator(Validator $validator): bool
	{
		return true;
	}

	public function makeOptional(): static
	{
		$this->optional = true;

		return $this;
	}

	public function input(mixed $value): static
	{
		$this->inputGiven = true;
		$this->value = new Field\Value($value);

		return $this;
	}

	public function prefill(mixed $defaultValue): static
	{
		$this->defaultValue = new Field\Value($defaultValue);

		return $this;
	}

	public function resolveValue(): static
	{
		if ($this->value->unwrap() === null) {
			$this->value = $this->defaultValue;
		}

		return $this;
	}

	public function validate(): Field\ValidationResult
	{
		$this->resolveValue();

		return (new Field\Validator(...$this->validators))->validate($this);
	}
}
