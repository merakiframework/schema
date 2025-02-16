<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\FieldSanitizer;
use Meraki\Schema\Attribute\Value;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

#[CoversClass(FieldSanitizer::class)]
abstract class FieldSanitizerTestCase extends TestCase
{

	#[Test]
	public function it_exists(): void
	{
		$sanitizer = $this->createSanitizer();

		$this->assertInstanceOf(FieldSanitizer::class, $sanitizer);
	}

	#[Test]
	public function it_is_a_field_sanitizer(): void
	{
		$sanitizer = $this->createSanitizer();

		$this->assertInstanceOf(FieldSanitizer::class, $sanitizer);
	}

	abstract protected function createSanitizer(): FieldSanitizer;
}
