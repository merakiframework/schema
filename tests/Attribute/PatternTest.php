<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\Attribute\Pattern;
use Meraki\Schema\AttributeTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Pattern::class)]
final class PatternTest extends AttributeTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$attribute = $this->createAttribute();

		$this->assertInstanceOf(Pattern::class, $attribute);
	}

	#[Test]
	public function it_is_a_constraint(): void
	{
		$attribute = $this->createAttribute();

		$this->assertInstanceOf(Constraint::class, $attribute);
	}

	public function getExpectedName(): string
	{
		return 'pattern';
	}

	public function getExpectedValue(): mixed
	{
		return '/[a-z]+/';
	}

	public function createAttribute(): Attribute
	{
		return new Pattern($this->getExpectedValue());
	}
}
