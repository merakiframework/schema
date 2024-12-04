<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\FieldValidationResult;
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
}
