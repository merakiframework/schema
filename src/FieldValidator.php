<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Exception\CheckTypeValidatorIsRequired;
use Meraki\Schema\Validator;
use Meraki\Schema\FieldValidationResult;
use Meraki\Schema\ValidatorValidationResult;
use Meraki\Schema\Validator\CheckType;
use Meraki\Schema\Validator\Dependent;
use Meraki\Schema\Validator\Set;
use InvalidArgumentException;
use RuntimeException;

final class FieldValidator
{
	private Set $validators;

	public function __construct(Validator ...$validators)
	{
		$this->validators = new Set(...$validators);

		$this->validators->assertCheckTypeValidatorExists();
	}

	public function validate(Field $field): FieldValidationResult
	{
		$results = new FieldValidationResult($field);
		/** @var array<class-string<Validator>, ValidationStatus> $statuses */
		$statuses = [];

		if ($field->optional && $field->value->notProvided()) {
			return $this->skipAll($field);
		}

		// run type validator first
		$typeValidator = $this->validators->getTypeValidator();
		$typePassed = $typeValidator->validate($field);
		$results = $results->add($this->createValidatorResultConditionally($typePassed, $typeValidator));
		$statuses[$typeValidator::class] = $this->toValidationStatus($typePassed);

		if (!$typePassed) {
			foreach ($this->validators->allExceptTypeValidator() as $validator) {
				$results = $results->add(ValidatorValidationResult::skip($validator));
				$statuses[$validator::class] = ValidationStatus::Skipped;
			}

			return $results;
		}

		// run independent/base validators
		foreach ($this->validators->baseValidators() as $validator) {
			$passed = $validator->validate($field);
			$results = $results->add($this->createValidatorResultConditionally($passed, $validator));
			$statuses[$validator::class] = $this->toValidationStatus($passed);
		}

		// run dependent validators in topological order
		foreach ($this->validators->sortDependentValidatorsByDependencies() as $validator) {
			$dependencies = $validator->dependsOn();
			$canRun = true;

			foreach ($dependencies as $dep) {
				if (!array_key_exists($dep, $statuses) || $statuses[$dep] !== ValidationStatus::Passed) {
					$canRun = false;
					break;
				}
			}

			if (!$canRun) {
				$results = $results->add(ValidatorValidationResult::skip($validator));
				$statuses[$validator::class] = ValidationStatus::Skipped;
				continue;
			}

			$passed = $validator->validate($field);
			$results = $results->add($this->createValidatorResultConditionally($passed, $validator));
			$statuses[$validator::class] = $this->toValidationStatus($passed);
		}

		return $results;
	}

	private function toValidationStatus(bool $passed): ValidationStatus
	{
		return $passed ? ValidationStatus::Passed : ValidationStatus::Failed;
	}

	private function createValidatorResultConditionally(bool $passed, Validator $validator): ValidatorValidationResult
	{
		return $passed ? ValidatorValidationResult::pass($validator) : ValidatorValidationResult::fail($validator);
	}

	private function skipAll(Field $field): FieldValidationResult
	{
		$results = new FieldValidationResult($field);

		foreach ($this->validators->all() as $validator) {
			$results = $results->add(ValidatorValidationResult::skip($validator));
		}

		return $results;
	}
}
