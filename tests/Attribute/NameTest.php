<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;
use Meraki\Schema\Attribute\Name;
use Meraki\Schema\AttributeTestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

#[CoversClass(Name::class)]
final class NameTest extends AttributeTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$attribute = $this->createAttribute();

		$this->assertInstanceOf(Name::class, $attribute);
	}

	#[Test]
	public function it_does_not_have_a_prefix_by_default(): void
	{
		/** @var Name */
		$attribute = $this->createAttribute();

		$this->assertEquals('', $attribute->prefix);
		$this->assertEquals($this->getExpectedValue(), $attribute->value);
	}

	#[Test]
	public function it_can_have_a_prefix(): void
	{
		/** @var Name */
		$attribute = $this->createAttribute();
		$prefix = 'prefix_';

		$attribute->prefixWith($prefix);

		$this->assertEquals($prefix, $attribute->prefix);
		$this->assertEquals($prefix . $this->getExpectedValue(), $attribute->value);
	}

	#[Test]
	public function it_can_remove_a_prefix(): void
	{
		/** @var Name */
		$attribute = $this->createAttribute();
		$prefix = 'prefix_';

		$attribute->prefixWith($prefix);
		$attribute->removePrefix();

		$this->assertEquals('', $attribute->prefix);
		$this->assertEquals($this->getExpectedValue(), $attribute->value);
	}

	public function getExpectedName(): string
	{
		return 'name';
	}

	public function getExpectedValue(): mixed
	{
		return 'field_name';
	}

	public function createAttribute(): Attribute
	{
		return new Name($this->getExpectedValue());
	}
}
