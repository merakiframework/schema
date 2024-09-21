<?php
declare(strict_types=1);

namespace Meraki\Form;

use Meraki\Form\Field\ValidationResult;
use Meraki\Form\Constraint;

class Field
{
	public function __construct(
		public string $name,
		public string $type,
		public Constraint\Set $constraints = new Constraint\Set()
	) {
	}

	public function require(): self
	{
		return $this->constrain(new Constraint\Required());
	}

	public function minLengthOf(int $minChars): self
	{
		return $this->constrain(new Constraint\Min($minChars));
	}

	public function maxLengthOf(int $maxChars): self
	{
		return $this->constrain(new Constraint\Max($maxChars));
	}

	public function minOf(int $minValue): self
	{
		return $this->constrain(new Constraint\Min($minValue));
	}

	public function maxOf(int $maxValue): self
	{
		return $this->constrain(new Constraint\Max($maxValue));
	}

	public function matches(string $pattern): self
	{
		return $this->constrain(new Constraint\Pattern($pattern));
	}

	public function constrain(Constraint $constraint): self
	{
		$this->constraints = $this->constraints->add($constraint);

		return $this;
	}

	public function validate(mixed $value): ValidationResult
	{
		$validationResult = ValidationResult::for($this);

		foreach ($this->constraints as $constraint) {
			$validationResult->addConstraintResult($constraint->validate($value));
		}

		return $validationResult;
	}

	public function __toArray(): array
	{
		return [
			'name' => $this->name,
			'type' => $this->type,
			'constraints' => $this->constraints->__toArray(),
		];
	}
}
