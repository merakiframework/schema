<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\AggregatedValidationResultTestCase;
use Meraki\Schema\Field;
use Meraki\Schema\Field\ValidationResult;
use Meraki\Schema\ValidationStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group('field')]
#[Group('validation')]
#[CoversClass(ValidationResult::class)]
final class ValidationResultTest extends AggregatedValidationResultTestCase
{
	public function createValidationResult(): ValidationResult
	{
		return new ValidationResult($this->mockField());
	}

	#[Test]
	public function it_can_retrieve_the_field_associated_with_result(): void
	{
		$field = $this->mockField();
		$result = new ValidationResult($field);

		$this->assertSame($field, $result->field);
	}

	#[Test]
	public function it_is_in_the_pending_state_if_no_results(): void
	{
		$result = new ValidationResult($this->mockField());

		$this->assertCount(0, $result);
		$this->assertCount(0, $result->getPending());
		$this->assertCount(0, $result->getPassed());
		$this->assertCount(0, $result->getFailed());
		$this->assertCount(0, $result->getSkipped());
		$this->assertEquals(ValidationStatus::Pending, $result->status);
	}

	private function mockField(): Field
	{
		return $this->createMock(Field::class);
	}
}
