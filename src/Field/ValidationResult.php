<?php
declare(strict_types=1);

namespace Meraki\Form\Field;

use Meraki\Form\Field;
use Meraki\Form\Constraint\ValidationResult as ConstraintValidationResult;

class ValidationResult
{
	public string $fieldName;
	protected array $constraintResults = [];
	protected bool $isValid = true;

	public function __construct(string $fieldName)
	{
		$this->fieldName = $fieldName;
	}

	public static function for(Field $field): self
	{
		return new self($field->name);
	}

	public function addConstraintResult(ConstraintValidationResult $result): void
	{
		$this->constraintResults[] = $result;

		if ($result->failed()) {
			$this->isValid = false;
		}
	}

	public function passed(): bool
	{
		return $this->isValid;
	}

	public function failed(): bool
	{
		return !$this->isValid;
	}

	public function getErrors(): array
	{
		$errors = [];

		/** @var ConstraintValidationResult $result */
		foreach ($this->constraintResults as $result) {
			if ($result->failed()) {
				$errors[] = $result->reason;
			}
		}

		return $errors;
	}
}
