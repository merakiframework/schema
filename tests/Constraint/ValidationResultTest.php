<?php
declare(strict_types=1);

namespace Meraki\Form\Constraint;

use Meraki\Form\Constraint;
use Meraki\Form\Constraint\ValidationResult;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ValidationResult::class)]
final class ValidationResultTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$result = new ValidationResult(ValidationResult::PASSED);

		$this->assertInstanceOf(ValidationResult::class, $result);
	}

	#[Test]
	public function it_creates_a_new_instance_that_passed(): void
	{
		$result = ValidationResult::pass();

		$this->assertTrue($result->passed());
		$this->assertFalse($result->failed());
	}

	#[Test]
	public function it_creates_a_new_instance_that_failed(): void
	{
		$reason = 'The value is too short.';
		$result = ValidationResult::fail($reason);

		$this->assertFalse($result->passed());
		$this->assertTrue($result->failed());
		$this->assertEquals($reason, $result->reason);
	}
}
