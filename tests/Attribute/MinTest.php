<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\Attribute\Min;
use Meraki\Schema\AttributeTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Min::class)]
final class MinTest extends AttributeTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$attribute = $this->createAttribute();

		$this->assertInstanceOf(Min::class, $attribute);
	}

	#[Test]
	public function it_is_a_constraint(): void
	{
		$attribute = $this->createAttribute();

		$this->assertInstanceOf(Constraint::class, $attribute);
	}

	public function getExpectedName(): string
	{
		return 'min';
	}

	public function getExpectedValue(): mixed
	{
		return 5;
	}

	public function createAttribute(): Attribute
	{
		return new Min($this->getExpectedValue());
	}
}
