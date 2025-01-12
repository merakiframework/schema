<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Field;
use Meraki\Schema\Attribute;
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
		$field = $this->createField()
			->prefill(null)
			->input(null);

		$this->assertInstanceOf(Field::class, $field);
	}

	#[Test]
	public function it_has_correct_type(): void
	{
		$expectedType = $this->getExpectedType();

		$field = $this->createField()
			->prefill(null)
			->input(null);

		$this->assertEquals($expectedType, $field->type);
	}

	#[Test]
	public function validation_is_in_pending_state_by_default(): void
	{
		$field = $this->createField();

		$this->assertTrue($field->validationResult->pending());
	}

	#[Test]
	public function validation_skipped_if_no_value_and_optional(): void
	{
		$field = $this->createField()
			->makeOptional()
			->prefill(null)
			->input(null);

		$this->assertTrue($field->validationResult->skipped());
		$this->assertValidationSkippedForConstraint($field, Attribute\Type::class);
	}

	#[Test]
	public function validation_passes_if_no_value_and_valid_default_value_and_optional(): void
	{
		$field = $this->createField()
			->makeOptional()
			->prefill($this->getValidValue())
			->input(null);

		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Type::class);
	}

	#[Test]
	public function validation_fails_if_no_value_and_invalid_default_value(): void
	{
		$field = $this->createField()
			->prefill($this->getInvalidValue())
			->input(null);

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Type::class);
	}

	#[Test]
	public function passes_validation_with_valid_value(): void
	{
		$field = $this->createField()
			->prefill(null)
			->input($this->getValidValue());

		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Type::class);
	}

	#[Test]
	public function fails_validation_with_invalid_value(): void
	{
		$field = $this->createField()
			->prefill(null)
			->input($this->getInvalidValue());

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Type::class);
	}

	#[Test]
	public function validation_fails_if_not_optional_and_no_value(): void
	{
		$field = $this->createField()
			->prefill(null)
			->input(null);

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Type::class);
	}

	#[Test]
	public function validation_fails_if_required_and_no_value_and_invalid_default_value(): void
	{
		$field = $this->createField()
			->prefill($this->getInvalidValue())
			->input(null);

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Type::class);
	}

	#[Test]
	public function validation_fails_if_required_and_no_value_and_valid_default_value(): void
	{
		$field = $this->createField()
			->prefill($this->getValidValue())
			->input(null);

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Type::class);
	}

	#[Test]
	public function skips_constraint_validation_if_optional_with_no_value_and_no_default(): void
	{
		if (!$this->usesConstraints()) {
			$this->markTestSkipped('This class does not use any constraints.');
			return;
		}

		$constraint = $this->createValidConstraint();
		$field = $this->createField()
			->makeOptional()
			->constrain($constraint)
			->prefill(null)
			->input(null);

		$this->assertTrue($field->validationResult->skipped());
		$this->assertValidationSkippedForConstraint($field, $constraint::class);
	}

	#[Test]
	public function skips_constraint_validation_if_value_is_invalid(): void
	{
		if (!$this->usesConstraints()) {
			$this->markTestSkipped('This class does not use any constraints.');
			return;
		}

		$constraint = $this->createValidConstraint();
		$field = $this->createField()
			->constrain($constraint)
			->prefill(null)
			->input($this->getInvalidValue());

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationSkippedForConstraint($field, $constraint::class);
	}

	#[Test]
	public function passes_constraint_validation_with_valid_value_and_valid_constraints(): void
	{
		if (!$this->usesConstraints()) {
			$this->markTestSkipped('This class does not use any constraints.');
			return;
		}

		$constraint = $this->createValidConstraint();
		$field = $this->createField()
			->constrain($constraint)
			->prefill(null)
			->input($this->getValidValue());

		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, $constraint::class);
	}

	#[Test]
	public function fails_constraint_validation_with_valid_value_and_invalid_constraints(): void
	{
		if (!$this->usesConstraints()) {
			$this->markTestSkipped('This class does not use any constraints.');
			return;
		}

		$constraint = $this->createInvalidConstraint();
		$field = $this->createField()
			->constrain($constraint)
			->prefill(null)
			->input($this->getValidValue());

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, $constraint::class);
	}

	abstract public function createField(): Field;
	abstract public function getExpectedType(): string;
	abstract public function getValidValue(): mixed;
	abstract public function getInvalidValue(): mixed;
	abstract public function createValidConstraint(): Constraint;
	abstract public function createInvalidConstraint(): Constraint;
	abstract public function usesConstraints(): bool;

	protected function toConstraintList(AggregatedConstraintValidationResults $results): array
	{
		$constraints = [];

		foreach ($results as $result) {
			$constraints[] = $result->constraint::class;
		}

		return $constraints;
	}

	protected function assertValidationPendingForConstraint(Field $field, string $fqcn): void
	{
		$this->assertHasResultForConstraint($field->validationResult->getPending(), $fqcn);
	}

	protected function assertValidationSkippedForConstraint(Field $field, string $fqcn): void
	{
		$this->assertHasResultForConstraint($field->validationResult->getSkipped(), $fqcn);
	}

	protected function assertValidationPassedForConstraint(Field $field, string $fqcn): void
	{
		$this->assertHasResultForConstraint($field->validationResult->getPasses(), $fqcn);
	}

	protected function assertValidationFailedForConstraint(Field $field, string $fqcn): void
	{
		$this->assertHasResultForConstraint($field->validationResult->getFailures(), $fqcn);
	}

	protected function assertHasResultForConstraint(AggregatedConstraintValidationResults $results, string $fqcn): void
	{
		$constraints = $this->toConstraintList($results);

		$this->assertContains($fqcn, $constraints);
	}
}
