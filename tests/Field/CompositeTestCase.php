<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Composite as CompositeField;
use Meraki\Schema\FieldTestCase;
use Meraki\Schema\ValidationStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

#[Group('field')]
#[CoversClass(CompositeField::class)]
abstract class CompositeTestCase extends FieldTestCase
{
	abstract public function createSubject(): CompositeField;

	#[Test]
	public function it_is_a_composite_field(): void
	{
		$this->assertInstanceOf(CompositeField::class, $this->createSubject());
	}

	#[Test]
	public function naming_a_composite_field_will_prefix_all_sub_fields(): void
	{
		$sut = $this->createSubject();

		foreach ($sut->fields as $field) {
			$this->assertStringStartsWith((string)$sut->name, (string)$field->name);
		}
	}

	#[Test]
	public function all_constraints_are_skipped_when_composite_field_is_optional_and_has_no_value(): void
	{
		$sut = $this->createSubject()->makeOptional()->input([]);

		$result = $sut->validate();

		foreach ($result as $fieldResult) {
			foreach ($fieldResult as $constraintResult) {
				$this->assertEquals(ValidationStatus::Skipped, $constraintResult->status, "Expected constraint '{$constraintResult->name}' to be skipped, but got '{$constraintResult->status->name}'.");
			}
		}
	}

	#[Test]
	public function all_constraints_are_skipped_for_optional_sub_field_when_no_value_provided(): void
	{
		$this->markTestSkipped('Most composite fields will require all sub-fields. This behaviour has been implemented though.');
	}

	public function assertConstraintValidationResultFailedForField(string $fieldName, string $constraintName, CompositeValidationResult $result): void
	{
		$this->assertConstraintValidationResultHasStatusOfForField(ValidationStatus::Failed, $fieldName, $constraintName, $result);
	}

	public function assertConstraintValidationResultPassedForField(string $fieldName, string $constraintName, CompositeValidationResult $result): void
	{
		$this->assertConstraintValidationResultHasStatusOfForField(ValidationStatus::Passed, $fieldName, $constraintName, $result);
	}

	public function assertConstraintValidationResultPendingForField(string $fieldName, string $constraintName, CompositeValidationResult $result): void
	{
		$this->assertConstraintValidationResultHasStatusOfForField(ValidationStatus::Pending, $fieldName, $constraintName, $result);
	}

	public function assertConstraintValidationResultSkippedForField(string $fieldName, string $constraintName, CompositeValidationResult $result): void
	{
		$this->assertConstraintValidationResultHasStatusOfForField(ValidationStatus::Skipped, $fieldName, $constraintName, $result);
	}

	public function assertConstraintValidationResultHasStatusOfForField(ValidationStatus $expectedStatus, string $fieldName, string $constraintName, CompositeValidationResult $result): void
	{
		$field = $result->get($fieldName);

		$this->assertNotNull($field, "Field '$fieldName' not found in validation result.");

		$constraint = $field->get($constraintName);

		$this->assertNotNull($constraint, "Constraint '$constraintName' not found in validation result for field '$fieldName'.");

		$this->assertEquals($expectedStatus, $constraint->status, "Expected status '{$expectedStatus->name}' for constraint '$constraintName' in field '$fieldName', but got '{$constraint->status->name}'.");
	}
}
