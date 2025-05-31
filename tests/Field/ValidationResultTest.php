<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\AggregatedValidationResultTestCase;
use Meraki\Schema\ValidationResult;
use Meraki\Schema\Field;
use Meraki\Schema\Field\ValidationResult as FieldValidationResult;
use Meraki\Schema\Field\ConstraintValidationResult;
use Meraki\Schema\ValidationStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group('field')]
#[Group('validation')]
#[CoversClass(ValidationResult::class)]
final class ValidationResultTest extends AggregatedValidationResultTestCase
{
	public function createSubject(ValidationResult ...$results): FieldValidationResult
	{
		return new FieldValidationResult($this->mockField(), ...$results);
	}

	public function createPassedResult(): ConstraintValidationResult
	{
		return ConstraintValidationResult::pass('type_passed');
	}

	public function createFailedResult(): ConstraintValidationResult
	{
		return ConstraintValidationResult::fail('type_failed');
	}

	public function createSkippedResult(): ConstraintValidationResult
	{
		return ConstraintValidationResult::skip('type_skipped');
	}

	public function createPendingResult(): ConstraintValidationResult
	{
		return new ConstraintValidationResult(ValidationStatus::Pending, 'type_pending');
	}

	#[Test]
	public function it_can_retrieve_the_field_associated_with_result(): void
	{
		$field = $this->mockField();
		$sut = new FieldValidationResult($field);

		$this->assertSame($field, $sut->field);
	}

	#[Test]
	public function it_is_in_the_pending_state_if_no_results(): void
	{
		$sut = $this->createSubject();

		$this->assertCount(0, $sut);
		$this->assertCount(0, $sut->getPending());
		$this->assertCount(0, $sut->getPassed());
		$this->assertCount(0, $sut->getFailed());
		$this->assertCount(0, $sut->getSkipped());
		$this->assertEquals(ValidationStatus::Pending, $sut->status);
	}

	#[Test]
	public function it_is_in_pending_state_when_any_result_is_pending(): void
	{
		$sut = $this->createSubject(
			ConstraintValidationResult::pass('type'),
			new ConstraintValidationResult(ValidationStatus::Pending, 'min'),
			ConstraintValidationResult::skip('max')
		);

		$this->assertEquals(ValidationStatus::Pending, $sut->status);
	}

	#[Test]
	public function it_is_in_failed_state_when_any_result_failed_and_none_pending(): void
	{
		$sut = $this->createSubject(
			ConstraintValidationResult::pass('type'),
			ConstraintValidationResult::fail('min'),
			ConstraintValidationResult::skip('max')
		);

		$this->assertEquals(ValidationStatus::Failed, $sut->status);
	}

	#[Test]
	public function it_is_in_passed_state_when_all_results_passed(): void
	{
		$sut = $this->createSubject(
			$this->createPassedResult(),
			ConstraintValidationResult::pass('min'),
			ConstraintValidationResult::pass('max')
		);

		$this->assertEquals(ValidationStatus::Passed, $sut->status);
	}

	#[Test]
	public function it_is_in_skipped_state_when_all_results_skipped(): void
	{
		$sut = $this->createSubject(
			$this->createSkippedResult(),
			ConstraintValidationResult::skip('min'),
			ConstraintValidationResult::skip('max')
		);

		$this->assertEquals(ValidationStatus::Skipped, $sut->status);
	}

	#[Test]
	public function it_is_in_passed_state_when_some_passed_and_some_skipped(): void
	{
		$sut = $this->createSubject(
			$this->createPassedResult(),
			ConstraintValidationResult::skip('min'),
			ConstraintValidationResult::pass('max')
		);

		$this->assertEquals(ValidationStatus::Passed, $sut->status);
	}

	#[Test]
	public function it_can_get_constraint_result_by_name(): void
	{
		$typeResult = ConstraintValidationResult::pass('type');
		$lengthResult = ConstraintValidationResult::fail('min');
		$formatResult = ConstraintValidationResult::skip('max');

		$sut = $this->createSubject($typeResult, $lengthResult, $formatResult);

		$this->assertSame($typeResult, $sut->get('type'));
		$this->assertSame($lengthResult, $sut->get('min'));
		$this->assertSame($formatResult, $sut->get('max'));
	}

	#[Test]
	public function it_returns_null_when_constraint_result_not_found(): void
	{
		$sut = $this->createSubject(
			ConstraintValidationResult::pass('type')
		);

		$this->assertNull($sut->get('nonexistent'));
	}

	#[Test]
	public function it_throws_exception_when_constraint_name_is_empty(): void
	{
		$sut = $this->createSubject(
			ConstraintValidationResult::pass('type')
		);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Constraint name cannot be empty');

		$sut->get('');
	}

	#[Test]
	public function it_throws_exception_when_duplicate_constraint_names_provided(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Duplicate constraint name: type');

		$this->createSubject(
			ConstraintValidationResult::pass('type'),
			ConstraintValidationResult::fail('type')
		);
	}

	#[Test]
	public function it_clones_the_field_when_cloned(): void
	{
		$originalField = $this->mockField();
		$sut = new FieldValidationResult($originalField);

		$cloned = clone $sut;

		$this->assertNotSame($originalField, $cloned->field);
		$this->assertEquals($originalField, $cloned->field);
	}

	#[Test]
	public function it_preserves_results_when_cloned(): void
	{
		$result = ConstraintValidationResult::pass('type');
		$sut = $this->createSubject($result);

		$cloned = clone $sut;

		$this->assertSame($result, $cloned->get('type'));
		$this->assertEquals($sut->status, $cloned->status);
	}

	#[Test]
	public function it_maintains_status_consistency_after_construction(): void
	{
		$sut = $this->createSubject(
			ConstraintValidationResult::pass('type'),
			ConstraintValidationResult::fail('min')
		);

		// Status should be calculated once during construction
		$initialStatus = $sut->status;

		// Access status multiple times to ensure it's consistent
		$this->assertEquals($initialStatus, $sut->status);
		$this->assertEquals($initialStatus, $sut->status);
		$this->assertEquals(ValidationStatus::Failed, $sut->status);
	}

	private function mockField(): Field
	{
		return $this->createMock(Field::class);
	}
}
