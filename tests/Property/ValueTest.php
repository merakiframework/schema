<?php
declare(strict_types=1);

namespace Meraki\Schema\Property;

use Meraki\Schema\Property\Value;
use Meraki\Schema\PropertyTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Value::class)]
final class ValueTest extends PropertyTestCase
{
	public function createProperty(): Value
	{
		return new Value('value');
	}

	#[Test]
	public function it_has_the_correct_name(): void
	{
		$property = new Value('value');

		$this->assertEquals('value', $property->name);
	}

	#[Test]
	public function it_sets_the_value_correctly(): void
	{
		$expectedValue = 'value';

		$property = new Value($expectedValue);

		$this->assertSame($expectedValue, $property->value);
	}

	#[Test]
	#[DataProvider('valuesConsideredAsProvided')]
	public function can_tell_if_value_is_provided(mixed $expectedValue): void
	{
		$property = new Value($expectedValue);

		$this->assertTrue($property->provided());
		$this->assertFalse($property->notProvided());
	}

	#[Test]
	public function can_tell_if_value_is_not_provided(): void
	{
		$property = new Value(null);

		$this->assertFalse($property->provided());
		$this->assertTrue($property->notProvided());
	}

	public static function valuesConsideredAsProvided(): array
	{
		return [
			'string' => ['test value'],
			'integer' => [123],
			'float' => [123.45],
			'boolean' => [true],
			'array' => [['item1', 'item2']],
			'object' => [(object)['property' => 'value']],
		];
	}
}
