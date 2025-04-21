<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\ValidationResult;
use Meraki\Schema\ValidationStatus;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

#[Group('validation')]
#[CoversClass(ValidationResult::class)]
abstract class ValidationResultTestCase extends TestCase
{
	#[Test]
	public function it_is_a_validation_result(): void
	{
		$result = $this->createValidationResult();

		$this->assertInstanceOf(ValidationResult::class, $result);
	}

	#[Test]
	public function it_has_a_status(): void
	{
		$result = $this->createValidationResult();

		$this->assertInstanceOf(ValidationStatus::class, $result->status);
	}

	abstract public function createValidationResult(): ValidationResult;
}
