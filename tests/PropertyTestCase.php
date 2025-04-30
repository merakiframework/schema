<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Property;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Property::class)]
abstract class PropertyTestCase extends TestCase
{
	abstract public function createProperty(): Property;

	#[Test]
	public function it_has_a_name(): void
	{
		$property = $this->createProperty();

		$this->assertObjectHasProperty('name', $property);
		$this->assertIsString($property->name);
	}

	#[Test]
	public function it_has_a_value(): void
	{
		$property = $this->createProperty();

		$this->assertObjectHasProperty('value', $property);
	}
}
