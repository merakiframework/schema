<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Field;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

#[CoversClass(Field::class)]
abstract class FieldTestCase extends TestCase
{
	#[Test]
	abstract public function it_exists(): void;

	#[Test]
	public function it_is_a_field(): void
	{
		$field = $this->createFieldWithNoValueAndNoDefaultValue();

		$this->assertInstanceOf(Field::class, $field);
	}

	#[Test]
	public function it_has_correct_type(): void
	{
		$expectedType = $this->getExpectedType();
		$field = $this->createFieldWithNoValueAndNoDefaultValue();

		$this->assertEquals($expectedType, $field->type);
	}

	#[Test]
	public function validation_skipped_if_no_value_and_optional(): void
	{
		$field = $this->createFieldWithNoValueAndNoDefaultValue()->makeOptional();

		$result = $field->validate();
		$this->assertTrue($result->skipped());

		$valueResult = $result->valueValidationResult;
		$this->assertTrue($valueResult->skipped());

		// $constraintResults = $result->constraintValidationResults;
		// $this->assertTrue($constraintResults->allSkipped());
	}

	#[Test]
	public function validation_passes_if_no_value_and_valid_default_value_and_optional(): void
	{
		$field = $this->createFieldWithNoValueAndValidDefaultValue()->makeOptional();

		$result = $field->validate();
		$this->assertTrue($result->passed());

		$valueResult = $result->valueValidationResult;
		$this->assertTrue($valueResult->passed());

		// $constraintResults = $result->constraintValidationResults;
		// $this->assertTrue($constraintResults->passed());
	}

	#[Test]
	public function validation_fails_if_no_value_and_invalid_default_value(): void
	{
		$field = $this->createFieldWithNoValueAndInvalidDefaultValue();

		$result = $field->validate();
		$this->assertTrue($result->failed());

		$valueResult = $result->valueValidationResult;
		$this->assertTrue($valueResult->failed());

		// $constraintResults = $result->constraintValidationResults;
		// $this->assertTrue($constraintResults->failed());
	}

	#[Test]
	public function passes_validation_with_valid_value(): void
	{
		$field = $this->createFieldWithValidValueAndNoDefaultValue();

		$result = $field->validate();
		$this->assertTrue($result->passed());

		$valueResult = $result->valueValidationResult;
		$this->assertTrue($valueResult->passed());

		// $constraintResults = $result->constraintValidationResults;
		// $this->assertTrue($constraintResults->allPassed());
	}

	#[Test]
	public function fails_validation_with_invalid_value(): void
	{
		$field = $this->createFieldWithInvalidValueAndNoDefaultValue();

		$result = $field->validate();
		$this->assertTrue($result->failed());

		$valueResult = $result->valueValidationResult;
		$this->assertTrue($valueResult->failed());

		// $constraintResults = $result->constraintValidationResults;
		// $this->assertTrue($constraintResults->skipped());
	}

	#[Test]
	public function validation_fails_if_not_optional_and_no_value(): void
	{
		$field = $this->createFieldWithNoValueAndNoDefaultValue();

		$result = $field->validate();
		$this->assertTrue($result->failed());

		$valueResult = $result->valueValidationResult;
		$this->assertTrue($valueResult->failed());

		// $constraintResults = $result->constraintValidationResults;
		// $this->assertTrue($constraintResults->skipped());
	}

	#[Test]
	public function validation_fails_if_not_optional_and_no_value_and_invalid_default_value(): void
	{
		$field = $this->createFieldWithNoValueAndInvalidDefaultValue();

		$result = $field->validate();
		$this->assertTrue($result->failed());

		$valueResult = $result->valueValidationResult;
		$this->assertTrue($valueResult->failed());

		// $constraintResults = $result->constraintValidationResults;
		// $this->assertTrue($constraintResults->skipped());
	}

	#[Test]
	public function validation_fails_if_not_optional_and_no_value_and_valid_default_value(): void
	{
		$field = $this->createFieldWithNoValueAndValidDefaultValue();

		$result = $field->validate();
		$this->assertTrue($result->failed());

		$valueResult = $result->valueValidationResult;
		$this->assertTrue($valueResult->failed());

		// $constraintResults = $result->constraintValidationResults;
		// $this->assertTrue($constraintResults->skipped());
	}

	#[Test]
	public function skips_constraint_validation_if_optional_with_no_value_and_no_default(): void
	{
		$constraint = $this->createValidConstraintForValidValue();

		if ($constraint === null) {
			$this->markTestSkipped('This class does not use any constraints.');
			return;
		}

		$field = $this->createFieldWithNoValueAndNoDefaultValue()
			->makeOptional()
			->constrain($constraint);

		$constraintResults = $field->validate()->constraintValidationResults;
		$this->assertTrue($constraintResults->skipped());
	}

	#[Test]
	public function skips_constraint_validation_if_value_is_invalid(): void
	{
		$constraint = $this->createValidConstraintForValidValue();

		if ($constraint === null) {
			$this->markTestSkipped('This class does not use any constraints.');
			return;
		}

		$field = $this->createFieldWithInvalidValueAndNoDefaultValue()->constrain($constraint);

		$constraintResults = $field->validate()->constraintValidationResults;
		$this->assertTrue($constraintResults->skipped());
	}

	#[Test]
	public function passes_constraint_validation_with_valid_value_and_valid_constraints(): void
	{
		$constraint = $this->createValidConstraintForValidValue();

		if ($constraint === null) {
			$this->markTestSkipped('This class does not use any constraints.');
			return;
		}

		$field = $this->createFieldWithValidValueAndNoDefaultValue()->constrain($constraint);

		$constraintResults = $field->validate()->constraintValidationResults;
		$this->assertTrue($constraintResults->passed());
	}

	#[Test]
	public function fails_constraint_validation_with_valid_value_and_invalid_constraints(): void
	{
		$constraint = $this->createInvalidConstraintForValidValue();

		if ($constraint === null) {
			$this->markTestSkipped('This class does not use any constraints.');
			return;
		}

		$field = $this->createFieldWithValidValueAndNoDefaultValue()->constrain($constraint);

		$constraintResults = $field->validate()->constraintValidationResults;
		$this->assertTrue($constraintResults->failed());
	}

	abstract public function createField(): Field;
	abstract public function getExpectedType(): string;
	abstract public function getValidValue(): mixed;
	abstract public function getInvalidValue(): mixed;
	abstract public function createValidConstraintForValidValue(): ?Constraint;
	abstract public function createInvalidConstraintForValidValue(): ?Constraint;

	public function createFieldWithNoValueAndNoDefaultValue(): Field
	{
		return $this->createField()->prefill(null)->input(null);
	}

	public function createFieldWithValidValueAndNoDefaultValue(): Field
	{
		return $this->createField()->prefill(null)->input($this->getValidValue());
	}

	public function createFieldWithInvalidValueAndNoDefaultValue(): Field
	{
		return $this->createField()->prefill(null)->input($this->getInvalidValue());
	}

	public function createFieldWithNoValueAndInvalidDefaultValue(): Field
	{
		return $this->createField()->prefill($this->getInvalidValue())->input(null);
	}

	public function createFieldWithValidValueAndInvalidDefaultValue(): Field
	{
		return $this->createField()->prefill($this->getInvalidValue())->input($this->getValidValue());
	}

	public function createFieldWithInvalidValueAndInvalidDefaultValue(): Field
	{
		return $this->createField()->prefill($this->getInvalidValue())->input($this->getInvalidValue());
	}

	public function createFieldWithNoValueAndValidDefaultValue(): Field
	{
		return $this->createField()->prefill($this->getValidValue())->input(null);
	}

	public function createFieldWithValidValueAndValidDefaultValue(): Field
	{
		return $this->createField()->prefill($this->getValidValue())->input($this->getValidValue());
	}

	public function createFieldWithInvalidValueAndValidDefaultValue(): Field
	{
		return $this->createField()->prefill($this->getValidValue())->input($this->getInvalidValue());
	}
}
