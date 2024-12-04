<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\ValidationResult;
use Meraki\Schema\ConstraintValidationResult;
use Meraki\Schema\Attribute;
use Meraki\Schema\Field;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ConstraintValidationResult::class)]
final class ConstraintValidationResultTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$result = $this->createValidationResult();

		$this->assertInstanceOf(ConstraintValidationResult::class, $result);
	}

	public function createValidationResult(int $status = ValidationResult::PASSED): ValidationResult
	{
		$attribute = new Attribute\Min(10);
		$field = new Field\Text(new Attribute\Name('test'));

		if ($status === ValidationResult::PASSED) {
			return ConstraintValidationResult::pass($attribute, $field);
		}

		return ConstraintValidationResult::fail($attribute, $field);
	}
}
