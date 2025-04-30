<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Property\Name;
use Meraki\Schema\PropertyTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Name::class)]
final class NameTest extends PropertyTestCase
{
	public function createProperty(): Name
	{
		return new Name('value');
	}

	#[Test]
	public function it_has_the_correct_name(): void
	{
		$property = new Name('value');

		$this->assertEquals('name', $property->name);
	}

	#[Test]
	public function it_sets_the_value_correctly(): void
	{
		$expectedValue = 'field_name';

		$property = new Name($expectedValue);

		$this->assertSame($expectedValue, $property->value);
	}
}
