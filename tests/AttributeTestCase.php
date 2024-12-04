<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Attribute;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Attribute::class)]
abstract class AttributeTestCase extends TestCase
{
	#[Test]
	abstract public function it_exists(): void;

	#[Test]
	public function it_is_an_attribute(): void
	{
		$attribute = $this->createAttribute();

		$this->assertInstanceOf(Attribute::class, $attribute);
	}

	#[Test]
	public function it_has_correct_name(): void
	{
		$expectedName = $this->getExpectedName();
		$attribute = $this->createAttribute();

		$this->assertEquals($expectedName, $attribute->name);
		$this->assertTrue($attribute->hasNameOf($expectedName));
	}

	public function can_get_value(): void
	{
		$attribute = $this->createAttribute();

		$this->assertEquals($this->getExpectedValue(), $attribute->value);
		$this->assertTrue($attribute->hasValueOf($this->getExpectedValue()));
	}

	abstract public function getExpectedName(): string;

	abstract public function getExpectedValue(): mixed;

	abstract public function createAttribute(): Attribute;
}
