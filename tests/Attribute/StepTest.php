<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\Attribute\Step;
use Meraki\Schema\AttributeTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Step::class)]
final class StepTest extends AttributeTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$attribute = $this->createAttribute();

		$this->assertInstanceOf(Step::class, $attribute);
	}

	#[Test]
	public function it_is_a_constraint(): void
	{
		$attribute = $this->createAttribute();

		$this->assertInstanceOf(Constraint::class, $attribute);
	}

	public function getExpectedName(): string
	{
		return 'step';
	}

	public function getExpectedValue(): mixed
	{
		return 3600;
	}

	public function createAttribute(): Attribute
	{
		return new Step($this->getExpectedValue());
	}
}
