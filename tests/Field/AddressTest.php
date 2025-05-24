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
	public function createSubject(): Address
	{
		return new Address(new Property\Name('test'));
	}

	public function createField(): Address
	{
		return new Address(new Property\Name('test'));
	}

	#[Test]
	public function it_has_the_correct_type(): void
	{
		$field = $this->createField();

		$this->assertSame('address', (string)$field->type);
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

	#[Test]
	public function it_has_no_value_by_default(): void
	{
		$field = $this->createSubject();

		$this->assertEquals([
			'test.street' => null,
			'test.city' => null,
			'test.state' => null,
			'test.postal_code' => null,
			'test.country' => null,
		], $field->value->unwrap());
		$this->assertEquals(null, $field->street->value->unwrap());
		$this->assertEquals(null, $field->city->value->unwrap());
		$this->assertEquals(null, $field->state->value->unwrap());
		$this->assertEquals(null, $field->postalCode->value->unwrap());
		$this->assertEquals(null, $field->country->value->unwrap());
	}

	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$field = $this->createSubject();

		$this->assertEquals([
			'test.street' => null,
			'test.city' => null,
			'test.state' => null,
			'test.postal_code' => null,
			'test.country' => null,
		], $field->defaultValue->unwrap());
		$this->assertEquals(null, $field->street->value->unwrap());
		$this->assertEquals(null, $field->city->value->unwrap());
		$this->assertEquals(null, $field->state->value->unwrap());
		$this->assertEquals(null, $field->postalCode->value->unwrap());
		$this->assertEquals(null, $field->country->value->unwrap());
	}
}
