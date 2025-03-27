<?php
declare(strict_types=1);

use Meraki\Schema\Sanitizer\EmptyStringToNull;
use Meraki\Schema\Attribute\Value;
use Meraki\Schema\FieldSanitizerTestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

#[CoversClass(EmptyStringToNull::class)]
final class EmptyStringToNullTest extends FieldSanitizerTestCase
{
	private EmptyStringToNull $sanitizer;

	protected function setUp(): void
	{
		$this->sanitizer = new EmptyStringToNull();
	}

	#[Test]
	public function it_can_convert_an_empty_string_to_null(): void
	{
		$value = Value::of('');

		$sanitizedValue = $this->sanitizer->sanitize($value);

		$this->assertNull($sanitizedValue->value);
	}

	#[Test]
	public function it_does_not_convert_a_non_empty_string_to_null(): void
	{
		$value = Value::of('not empty');

		$sanitizedValue = $this->sanitizer->sanitize($value);

		$this->assertSame('not empty', $sanitizedValue->value);
	}

	protected function createSanitizer(): EmptyStringToNull
	{
		return new EmptyStringToNull();
	}
}
