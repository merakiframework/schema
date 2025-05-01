<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Address;
use Meraki\Schema\Field\Composite;
use Meraki\Schema\Property;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group('field')]
#[CoversClass(Address::class)]
final class AddressTest extends FieldTestCase
{
	public function createField(): Address
	{
		return new Address(new Property\Name('test'));
	}

	#[Test]
	public function it_has_the_correct_type(): void
	{
		$field = $this->createField();

		$this->assertSame('address', $field->type->value);
	}

	#[Test]
	public function it_has_the_correct_name(): void
	{
		$field = $this->createField();

		$this->assertSame('test', $field->name->value);
	}

	#[Test]
	public function it_is_a_composite_field(): void
	{
		$field = $this->createField();

		$this->assertInstanceOf(Composite::class, $field);
	}
}
