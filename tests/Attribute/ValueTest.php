<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\AttributeTestCase;
use Meraki\Schema\Attribute\Value;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

#[CoversClass(Value::class)]
final class ValueTest extends AttributeTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$attribute = $this->createAttribute();

		$this->assertInstanceOf(Value::class, $attribute);
	}

	#[Test]
	public function has_a_named_constructor(): void
	{
		$attribute = Value::of(null);

		$this->assertInstanceOf(Value::class, $attribute);
		$this->assertNull($attribute->value);
		$this->assertTrue($attribute->hasValueOf(null));
	}

	#[Test]
	public function can_get_new_instance_from_default_value(): void
	{
		$defaultValue = 'hello';
		$value = Value::of(null)->defaultsTo($defaultValue);

		$this->assertInstanceOf(Value::class, $value);
		$this->assertEquals($defaultValue, $value->defaultValue);
	}

	public function getExpectedName(): string
	{
		return 'value';
	}

	public function getExpectedValue(): mixed
	{
		return null;
	}

	public function createAttribute(): Attribute
	{
		return new Value($this->getExpectedValue());
	}
}
