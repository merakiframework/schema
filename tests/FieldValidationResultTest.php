<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\FieldValidationResult;
use Meraki\Schema\AggregatedValidationResults;
use Meraki\Schema\ValidationResult;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

#[CoversClass(FieldValidationResult::class)]
final class FieldValidationResultTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$this->assertTrue(class_exists(FieldValidationResult::class));
	}

	#[Test]
	public function it_is_a_validation_result(): void
	{
		$result = new FieldValidationResult();

		$this->assertInstanceOf(ValidationResult::class, $result);
	}

	#[Test]
	public function it_is_an_aggregation_of_validation_results(): void
	{
		$result = new FieldValidationResult();

		$this->assertInstanceOf(AggregatedValidationResults::class, $result);
	}

	#[Test]
	public function it_is_in_the_pending_state_if_no_results(): void
	{
		$result = new FieldValidationResult();

		$this->assertCount(0, $result);
		$this->assertTrue($result->pending());
		$this->assertFalse($result->passed());
		$this->assertFalse($result->failed());
		$this->assertFalse($result->skipped());
		$this->assertEquals(ValidationResult::PENDING, $result->status);
	}
}
