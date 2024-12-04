<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\Attribute\OneOf;
use Meraki\Schema\AttributeTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(OneOf::class)]
final class OneOfTest extends AttributeTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$attribute = $this->createAttribute();

		$this->assertInstanceOf(OneOf::class, $attribute);
	}

	#[Test]
	public function it_is_a_constraint(): void
	{
		$attribute = $this->createAttribute();

		$this->assertInstanceOf(Constraint::class, $attribute);
	}

	public function getExpectedName(): string
	{
		return 'one_of';
	}

	public function getExpectedValue(): mixed
	{
		return ['a', 'b', 'c'];
	}

	public function createAttribute(): Attribute
	{
		return new OneOf($this->getExpectedValue());
	}
}
