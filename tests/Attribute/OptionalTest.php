<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;
use Meraki\Schema\Attribute\Optional;
use Meraki\Schema\AttributeTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Optional::class)]
final class OptionalTest extends AttributeTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$attribute = $this->createAttribute();

		$this->assertInstanceOf(Optional::class, $attribute);
	}

	#[Test]
	public function it_creates_a_new_instance_with_default_value(): void
	{
		$optional = $this->createAttribute();

		$this->assertTrue($optional->value);
		$this->assertTrue($optional->hasValueOf(true));
	}

	#[Test]
	public function it_creates_a_new_instance_with_custom_value(): void
	{
		$optional = new Optional(false);

		$this->assertFalse($optional->value);
		$this->assertTrue($optional->hasValueOf(false));
	}

	public function getExpectedName(): string
	{
		return 'optional';
	}

	public function getExpectedValue(): mixed
	{
		return true;
	}

	public function createAttribute(): Attribute
	{
		return new Optional();
	}
}
