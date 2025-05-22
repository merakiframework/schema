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
	/**
	 * The class under test.
	 */
	abstract public function createSubject(ValidationResult ...$results): ValidationResult;

	#[Test]
	public function it_is_a_validation_result(): void
	{
		$sut = $this->createSubject();

		$this->assertInstanceOf(ValidationResult::class, $sut);
	}

	#[Test]
	public function it_has_a_status(): void
	{
		$sut = $this->createSubject();

		$this->assertObjectHasProperty('status', $sut);
		$this->assertInstanceOf(ValidationStatus::class, $sut->status);
	}
}
