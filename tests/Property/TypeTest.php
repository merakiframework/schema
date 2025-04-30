<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Property\Type;
use Meraki\Schema\PropertyTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Type::class)]
final class TypeTest extends PropertyTestCase
{
	public function createProperty(): Type
	{
		return new Type('value');
	}

	#[Test]
	public function it_has_the_correct_name(): void
	{
		$property = new Type('value');

		$this->assertEquals('type', $property->name);
	}

	#[Test]
	public function it_sets_the_value_correctly(): void
	{
		$expectedValue = 'field_name';

		$property = new Type($expectedValue);

		$this->assertSame($expectedValue, $property->value);
	}
}
