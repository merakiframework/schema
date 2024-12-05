<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Property;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

#[CoversClass(Property::class)]
abstract class PropertyTestCase extends TestCase
{
	#[Test]
	public function has_a_name(): void
	{
		$this->assertObjectHasProperty('name', $this->createProperty());
	}

	#[Test]
	public function has_a_value(): void
	{
		$this->assertObjectHasProperty('value', $this->createProperty());
	}

	abstract protected function createProperty(): Property;
}
