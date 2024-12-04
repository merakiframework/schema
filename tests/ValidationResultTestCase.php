<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\ValidationResult;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(ValidationResult::class)]
abstract class ValidationResultTestCase extends TestCase
{
	#[Test]
	abstract public function it_exists(): void;

	#[Test]
	public function it_is_a_validation_result(): void
	{
		$result = $this->createValidationResult();

		$this->assertInstanceOf(ValidationResult::class, $result);
	}

	#[Test]
	public function can_create_a_new_instance_that_passed(): void
	{
		$result = $this->createValidationResult();

		$this->assertTrue($result->passed());
		$this->assertFalse($result->failed());
	}

	#[Test]
	public function can_create_a_new_instance_that_failed(): void
	{
		$result = $this->createValidationResult(ValidationResult::FAILED);

		$this->assertFalse($result->passed());
		$this->assertTrue($result->failed());
	}

	abstract public function createValidationResult(int $status = ValidationResult::PASSED): ValidationResult;
}
