<?php
declare(strict_types=1);

namespace Meraki\Schema\Validator;

use Meraki\Schema\Validator as ValidatorInterface;
use Meraki\Schema\ValidationStatus;
use Meraki\Schema\Validator\ValidationResult;
use Meraki\Schema\ValidationResultTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('validation')]
#[CoversClass(ValidationResult::class)]
final class ValidationResultTest extends ValidationResultTestCase
{
	public function createValidationResult(): ValidationResult
	{
		return new ValidationResult(ValidationStatus::Passed, $this->mockValidator());
	}

	#[Test]
	public function can_retrieve_validator_associated_with_result(): void
	{
		$validator = $this->mockValidator();
		$result = new ValidationResult(ValidationStatus::Passed, $validator);

		$this->assertSame($validator, $result->validator);
	}

	private function mockValidator(): ValidatorInterface
	{
		return $this->createMock(ValidatorInterface::class);
	}
}
