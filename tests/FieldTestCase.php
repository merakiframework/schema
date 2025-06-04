<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Field;
use Meraki\Schema\Field\ValidationResult;
use Meraki\Schema\Field\ConstraintValidationResult;
use Meraki\Schema\Property;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group('field')]
#[CoversClass(Field::class)]
abstract class FieldTestCase extends TestCase
{
	abstract public function createField(): Field;

	#[Test]
	public function it_has_a_type(): void
	{
		$field = $this->createField();

		$this->assertInstanceOf(Property\Type::class, $field->type);
	}

	#[Test]
	public function it_has_a_name(): void
	{
		$field = $this->createField();

		$this->assertInstanceOf(Property\Name::class, $field->name);
	}

	#[Test]
	public function it_is_required_by_default(): void
	{
		$field = $this->createField();

		$this->assertFalse($field->optional);
	}

	#[Test]
	public function it_can_be_made_optional(): void
	{
		$field = $this->createField()
			->makeOptional();

		$this->assertTrue($field->optional);
	}

	#[Test]
	public function no_input_has_been_given_by_default(): void
	{
		$field = $this->createField();

		$this->assertFalse($field->inputGiven);
	}

	#[Test]
	public function it_can_tell_when_input_has_been_given(): void
	{
		$field = $this->createField()
			->input(null);

		$this->assertTrue($field->inputGiven);
	}

	#[Test]
	public function it_resolves_to_default_value_when_no_input_given(): void
	{
		$field = $this->createField();

		$this->assertEquals($field->defaultValue, $field->resolvedValue);
	}

	#[Test]
	public function it_returns_fluent_interface_for_input(): void
	{
		$field = $this->createField();

		$result = $field->input(null);

		$this->assertSame($field, $result);
	}

	// #[Test]
	// public function it_fails_validation_when_required_and_no_value_provided(): void
	// {
	// 	$field = $this->createField()
	// 		->input(null);

	// 	$result = $field->validate();

	// 	$this->assertEquals(ValidationStatus::Failed, $result->status);
	// 	$this->assertConstraintValidationResultFailed('type', $result);
	// }

	#[Test]
	public function it_skips_all_constraints_when_optional_and_no_value_provided(): void
	{
		$field = $this->createField()
			->makeOptional()
			->input(null);

		$result = $field->validate();

		$this->assertEquals(ValidationStatus::Skipped, $result->status);
	}

	// #[Test]
	// abstract public function it_returns_fluent_interface_for_prefill(): void;

	// #[Test]
	// abstract public function it_has_the_correct_name(): void;

	// #[Test]
	// abstract public function it_has_no_value_by_default(): void;

	// #[Test]
	// abstract public function it_has_no_default_value_by_default(): void;

	public function assertConstraintValidationResultPassed(string $constraintName, ValidationResult $result): void
	{
		$this->assertConstraintValidationResultHasStatusOf(ValidationStatus::Passed, $constraintName, $result);
	}

	public function assertConstraintValidationResultSkipped(string $constraintName, ValidationResult $result): void
	{
		$this->assertConstraintValidationResultHasStatusOf(ValidationStatus::Skipped, $constraintName, $result);
	}

	public function assertConstraintValidationResultFailed(string $constraintName, ValidationResult $result): void
	{
		$this->assertConstraintValidationResultHasStatusOf(ValidationStatus::Failed, $constraintName, $result);
	}

	public function assertConstraintValidationResultHasStatusOf(ValidationStatus $expectedStatus, string $constraintName, ValidationResult $result): void
	{
		/** @var ConstraintValidationResult $constraintResult */
		foreach ($result as $constraintResult) {
			if ($constraintResult->name === $constraintName) {
				$this->assertEquals($expectedStatus, $constraintResult->status);
				return;
			}
		}
	}
}
