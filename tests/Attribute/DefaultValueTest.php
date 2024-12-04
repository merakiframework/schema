<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\AttributeTestCase;
use Meraki\Schema\Attribute\DefaultValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(DefaultValue::class)]
final class DefaultValueTest extends AttributeTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$attribute = $this->createAttribute();

		$this->assertInstanceOf(DefaultValue::class, $attribute);
	}

	public function getExpectedName(): string
	{
		return 'default_value';
	}

	public function getExpectedValue(): mixed
	{
		return null;
	}

	public function createAttribute(): Attribute
	{
		return new DefaultValue($this->getExpectedValue());
	}
}
