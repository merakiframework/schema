<?php
declare(strict_types=1);

namespace Meraki\Form;

use Meraki\Form\Field\ValidationResult as FieldValidationResult;

class ValidationResult
{
	public array $errors = [];

	public function __construct(public Schema $schema)
	{
	}

	public function addFieldResult(FieldValidationResult $result): void
	{
		if (!isset($this->errors[$result->fieldName])) {
			$this->errors[$result->fieldName] = [];
		}

		if ($result->failed()) {
			$this->errors[$result->fieldName] = array_merge($this->errors[$result->fieldName], $result->getErrors());
		}
	}

	public function passed(): bool
	{
		$errorCount = 0;

		foreach ($this->errors as $fieldName => $errors) {
			$errorCount += count($errors);
		}

		return $errorCount === 0;
	}

	public function failed(): bool
	{
		return !$this->passed();
	}
}
