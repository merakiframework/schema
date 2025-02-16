<?php
declare(strict_types=1);

use Meraki\Schema\Sanitizer\ConvertOnOffToBoolean;
use Meraki\Schema\Attribute\Value;
use Meraki\Schema\FieldSanitizerTestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

#[CoversClass(ConvertOnOffToBoolean::class)]
final class ConvertOnOffToBooleanTest extends FieldSanitizerTestCase
{
	private ConvertOnOffToBoolean $sanitizer;

	protected function setUp(): void
	{
		$this->sanitizer = new ConvertOnOffToBoolean();
	}

	#[Test]
	public function it_should_convert_on_to_true(): void
	{
		$value = Value::of('on');

		$sanitizedValue = $this->sanitizer->sanitize($value);

		$this->assertTrue($sanitizedValue->value);
	}

	#[Test]
	public function it_should_convert_on_to_true_case_insensitive(): void
	{
		$value = Value::of('ON');

		$sanitizedValue = $this->sanitizer->sanitize($value);

		$this->assertTrue($sanitizedValue->value);
	}

	#[Test]
	public function it_should_convert_off_to_false(): void
	{
		$value = Value::of('off');

		$sanitizedValue = $this->sanitizer->sanitize($value);

		$this->assertFalse($sanitizedValue->value);
	}

	#[Test]
	public function it_should_convert_off_to_false_case_insensitive(): void
	{
		$value = Value::of('OFF');

		$sanitizedValue = $this->sanitizer->sanitize($value);

		$this->assertFalse($sanitizedValue->value);
	}

	#[Test]
	public function it_should_leave_other_values_unchanged(): void
	{
		$value = Value::of('other');

		$sanitizedValue = $this->sanitizer->sanitize($value);

		$this->assertSame('other', $sanitizedValue->value);
	}

	protected function createSanitizer(): ConvertOnOffToBoolean
	{
		return new ConvertOnOffToBoolean();
	}
}
