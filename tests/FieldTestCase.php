<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Field;
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
	public function it_resolves_to_the_default_value_when_nothing_is_submitted(): void
	{
		$field = $this->createField();

		$resolved = $field->resolve(null);

		$this->assertEquals($field->defaultValue->unwrap(), $resolved->value);
	}

	#[Test]
	public function it_reports_what_was_submitted_unchanged(): void
	{
		$field = $this->createField();

		$resolved = $field->resolve(null);

		$this->assertNull($resolved->given);
	}

	#[Test]
	public function resolving_does_not_check_the_value(): void
	{
		$field = $this->createField();

		$resolved = $field->resolve(null);

		$this->assertSame(ValidationStatus::Pending, $resolved->status);
	}

	#[Test]
	public function it_fails_the_shape_when_required_and_nothing_is_submitted(): void
	{
		$field = $this->createField();

		$result = $field->validate(null);

		$this->assertSame(ValidationStatus::Failed, $result->status);
		$this->assertConstraintValidationResultFailed('type', $result);
	}

	#[Test]
	public function it_skips_all_constraints_when_optional_and_no_value_provided(): void
	{
		$field = $this->createField()
			->makeOptional();

		$result = $field->validate(null);

		$this->assertEquals(ValidationStatus::Skipped, $result->status);
	}

	#[Test]
	public function validating_the_same_field_twice_does_not_change_it(): void
	{
		$field = $this->createField();
		$before = clone $field;

		$field->validate(null);

		$this->assertEquals($before, $field);
	}

	public function assertConstraintValidationResultPassed(string $constraintName, AggregatedValidationResult $result): void
	{
		$this->assertConstraintValidationResultHasStatusOf(ValidationStatus::Passed, $constraintName, $result);
	}

	public function assertConstraintValidationResultSkipped(string $constraintName, AggregatedValidationResult $result): void
	{
		$this->assertConstraintValidationResultHasStatusOf(ValidationStatus::Skipped, $constraintName, $result);
	}

	public function assertConstraintValidationResultFailed(string $constraintName, AggregatedValidationResult $result): void
	{
		$this->assertConstraintValidationResultHasStatusOf(ValidationStatus::Failed, $constraintName, $result);
	}

	public function assertConstraintValidationResultHasStatusOf(ValidationStatus $expectedStatus, string $constraintName, AggregatedValidationResult $result): void
	{
		$reported = [];

		/** @var ConstraintValidationResult $constraintResult */
		foreach ($result as $constraintResult) {
			if ($constraintResult->name === $constraintName) {
				$this->assertEquals($expectedStatus, $constraintResult->status);
				return;
			}

			$reported[] = $constraintResult->name;
		}

		// Falling through used to pass silently, so a test naming a constraint that was
		// never reported asserted nothing at all.
		$this->fail(sprintf(
			'No result was reported for constraint "%s". Reported: %s.',
			$constraintName,
			implode(', ', $reported) ?: 'none',
		));
	}
}
