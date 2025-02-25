<?php
declare(strict_types=1);

use Meraki\Schema\Sanitizer\CoerceStringToNumber;
use Meraki\Schema\Attribute\Value;
use Meraki\Schema\FieldSanitizerTestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass, DataProvider};

#[CoversClass(CoerceStringToNumber::class)]
final class CoerceStringToNumberTest extends FieldSanitizerTestCase
{
	private CoerceStringToNumber $sanitizer;

	protected function setUp(): void
	{
		$this->sanitizer = new CoerceStringToNumber();
	}

	#[Test]
	#[DataProvider('validNumbers')]
	public function it_can_coerce_valid_string_number_to_actual_number(string $unsanitizedNumber, int|float $expectedNumber): void
	{
		$value = Value::of($unsanitizedNumber);

		$sanitizedValue = $this->sanitizer->sanitize($value);

		$this->assertSame($expectedNumber, $sanitizedValue->value);
	}

	#[Test]
	#[DataProvider('invalidNumbers')]
	public function it_leaves_invalid_numbers_alone(mixed $nan): void
	{
		$value = Value::of($nan);

		$sanitizedValue = $this->sanitizer->sanitize($value);

		$this->assertSame($nan, $sanitizedValue->value);
	}

	public static function validNumbers(): array
	{
		return [
			'positive integer' => ['123', 123],
			'negative integer' => ['-123', -123],
			'integer with leading 0' => ['0123', 123],
			'integer with leading + sign' => ['+123', 123],
			'integer with leading + sign and leading 0' => ['+0123', 123],
			'integer with leading - sign and leading 0' => ['-0123', -123],
			'positive float' => ['0.456', 0.456],
			'negative float' => ['-0.456', -0.456],
			'float without leading digit' => ['.456', 0.456],
			'float with missing digits after decimal' => ['123.', 123.0],
			'scientific notation' => ['1.23e3', 1.23e3],
			'scientific notation with negative exponent' => ['1.23e-3', 1.23e-3],
			'scientific notation with positive signs' => ['+1.23e+3', 1.23e+3],
			'zero' => ['0', 0],
			'positive zero' => ['+0', 0],
			'negative zero' => ['-0', 0],
		];
	}

	public static function invalidNumbers(): array
	{
		return [
			'only letters' => ['abc'],
			'letters and numbers' => ['abc123'],
			'letters and symbols' => ['abc!@#'],
			'letters, numbers, and symbols' => ['abc123!@#'],
			'symbols' => ['!@#'],
			'whitespace' => [' '],
			'nothing' => [''],
			'null' => [null],
			'boolean' => [true],
			'multiple decimal points' => ['1.23.4'],
			'missing number in exponent' => ['1e'],
			'double negative sign' => ['--123'],
			'double positive sign' => ['++123'],
			'leading whitespace' => [' 123'],
			'trailing whitespace' => ['123 '],
			'leading and trailing whitespace' => [' 123 '],
			'double up on exponent' => ['1.23e3e3'],
			'mixed signs' => ['+123-456'],
			'mixed leading signs' => ['+-123'],
		];
	}

	protected function createSanitizer(): CoerceStringToNumber
	{
		return new CoerceStringToNumber();
	}
}
