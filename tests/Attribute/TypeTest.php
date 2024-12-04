<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;
use Meraki\Schema\Attribute\Type;
use Meraki\Schema\AttributeTestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

#[CoversClass(Type::class)]
final class TypeTest extends AttributeTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$attribute = $this->createAttribute();

		$this->assertInstanceOf(Type::class, $attribute);
	}

	public function getExpectedName(): string
	{
		return 'type';
	}

	public function getExpectedValue(): mixed
	{
		return 'number';
	}

	public function createAttribute(): Attribute
	{
		return new Type($this->getExpectedValue());
	}
}
