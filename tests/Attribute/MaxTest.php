<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\Attribute\Max;
use Meraki\Schema\AttributeTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Max::class)]
final class MaxTest extends AttributeTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$attribute = $this->createAttribute();

		$this->assertInstanceOf(Max::class, $attribute);
	}

	#[Test]
	public function it_is_a_constraint(): void
	{
		$attribute = $this->createAttribute();

		$this->assertInstanceOf(Constraint::class, $attribute);
	}

	public function getExpectedName(): string
	{
		return 'max';
	}

	public function getExpectedValue(): mixed
	{
		return 5;
	}

	public function createAttribute(): Attribute
	{
		return new Max($this->getExpectedValue());
	}
}
