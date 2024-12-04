<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Validator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Validator::class)]
abstract class ValidatorTestCase extends TestCase
{
	#[Test]
	abstract public function it_exists(): void;

	#[Test]
	public function is_a_validator(): void
	{
		$validator = $this->createValidator();

		$this->assertInstanceOf(Validator::class, $validator);
	}

	abstract public function createValidator(): Validator;
}
