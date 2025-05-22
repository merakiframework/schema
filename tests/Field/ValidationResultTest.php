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
		return ConstraintValidationResult::pass('type');
	}

	public function createFailedResult(): ConstraintValidationResult
	{
		return ConstraintValidationResult::fail('type');
	}

	public function createSkippedResult(): ConstraintValidationResult
	{
		return ConstraintValidationResult::skip('type');
	}

	public function createPendingResult(): ConstraintValidationResult
	{
		return new ConstraintValidationResult(ValidationStatus::Pending, 'type');
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

	private function mockField(): Field
	{
		return $this->createMock(Field::class);
	}
}
