<?php
declare(strict_types=1);

namespace Meraki\Schema;

use DateTimeImmutable;
use Meraki\Schema\InputTypeConverter;
use Meraki\Schema\Exception\InputTypeNotRegistered;
use Meraki\Schema\Exception\InputTypeConversionFailed;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(InputTypeConverter::class)]
final class InputTypeConverterTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$this->assertTrue(class_exists(InputTypeConverter::class));
	}

	#[Test]
	public function it_converts_strings(): void
	{
		$converter = new InputTypeConverter();

		$result = $converter->convert(['foo' => '123'], ['foo' => 'string']);

		$this->assertSame('123', $result['foo']);
	}

	#[Test]
	public function it_converts_ints(): void
	{
		$converter = new InputTypeConverter();

		$result = $converter->convert(['foo' => '123'], ['foo' => 'int']);

		$this->assertSame(123, $result['foo']);
	}

	#[Test]
	public function it_converts_integers(): void
	{
		$converter = new InputTypeConverter();

		$result = $converter->convert(['foo' => '123'], ['foo' => 'integer']);

		$this->assertSame(123, $result['foo']);
	}

	#[Test]
	public function it_converts_floats(): void
	{
		$converter = new InputTypeConverter();

		$result = $converter->convert(['foo' => '123.45'], ['foo' => 'float']);

		$this->assertSame(123.45, $result['foo']);
	}

	#[Test]
	#[DataProvider('validBooleanProvider')]
	public function it_converts_booleans(string $inputValue, bool $expectedValue): void
	{
		$converter = new InputTypeConverter();

		$result = $converter->convert(['foo' => $inputValue], ['foo' => 'boolean']);

		$this->assertSame($expectedValue, $result['foo']);
	}

	#[Test]
	#[DataProvider('validBooleanProvider')]
	public function it_converts_bools(string $inputValue, bool $expectedValue): void
	{
		$converter = new InputTypeConverter();

		$result = $converter->convert(['foo' => $inputValue], ['foo' => 'bool']);

		$this->assertSame($expectedValue, $result['foo']);
	}

	public static function validBooleanProvider(): array
	{
		return [
			'string true' => ['true', true],
			'string false' => ['false', false],
			'string 1' => ['1', true],
			'string 0' => ['0', false],
			'string "yes"' => ['yes', true],
			'string "no"' => ['no', false],
			'string "on"' => ['on', true],
			'string "off"' => ['off', false],
		];
	}

	#[Test]
	public function it_converts_nested_dot_syntax_keys(): void
	{
		$converter = new InputTypeConverter();

		$converter->registerType('string', fn($v) => (string) $v);

		$data = ['user' => ['email' => 123]];
		$types = ['user.email' => 'string'];

		$result = $converter->convert($data, $types);

		$this->assertSame(['user' => ['email' => '123']], $result);
	}

	#[Test]
	public function it_sets_null_when_field_is_missing_and_force_null_is_true(): void
	{
		$converter = new InputTypeConverter(true);

		$result = $converter->convert([], ['missing' => 'string']);

		$this->assertArrayHasKey('missing', $result);
		$this->assertNull($result['missing']);
	}

	#[Test]
	public function it_skips_missing_fields_when_force_null_is_false(): void
	{
		$converter = new InputTypeConverter(false);

		$result = $converter->convert([], ['missing' => 'string']);

		$this->assertSame([], $result);
	}

	#[Test]
	public function it_throws_when_type_is_not_registered(): void
	{
		$converter = new InputTypeConverter();

		$this->expectException(InputTypeNotRegistered::class);

		$converter->convert(['foo' => 'bar'], ['foo' => 'unknown']);
	}

	#[Test]
	public function it_supports_custom_type_casters(): void
	{
		$converter = new InputTypeConverter();
		$converter->registerType('datetime', function(mixed $v): DateTimeImmutable {
			return new DateTimeImmutable($v);
		});

		$result = $converter->convert(['when' => '2025-04-13T09:00:00'], ['when' => 'datetime']);

		$this->assertEquals(
			(new DateTimeImmutable('2025-04-13T09:00:00'))->format('Y-m-d\TH:i:s'),
			$result['when']->format('Y-m-d\TH:i:s')
		);
	}

	#[Test]
	public function it_throws_if_custom_caster_fails(): void
	{
		$converter = new InputTypeConverter();

		$converter->registerType('fail', function (): void {
			throw new RuntimeException('Conversion failed!');
		});

		$this->expectException(InputTypeConversionFailed::class);

		$converter->convert(['bad' => 'value'], ['bad' => 'fail']);
	}
}
