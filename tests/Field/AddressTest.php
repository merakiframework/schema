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
final class AddressTest extends CompositeTestCase
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

	#[Test]
	public function it_serializes_and_deserializes(): void
	{
		$address = [
			'test.street' => '123 Main St',
			'test.city' => 'Craigmore',
			'test.state' => 'SA',
			'test.postal_code' => '5112',
			'test.country' => 'Australia'
		];
		$sut = $this->createSubject()
			->prefill($address);

		$serialized = $sut->serialize();

		// serializing normalises time strings
		$this->assertEquals('address', $serialized->type);
		$this->assertEquals('test', $serialized->name);
		$this->assertFalse($serialized->optional);
		$this->assertEquals($address, $serialized->value);

		$deserialized = Address::deserialize($serialized);

		$this->assertEquals('address', $deserialized->type->value);
		$this->assertEquals('test', $deserialized->name->value);
		$this->assertFalse($deserialized->optional);
		$this->assertEquals($address, $deserialized->defaultValue->unwrap());
	}

	#[Test]
	public function children_returns_serialized_fields(): void
	{
		$field = $this->createSubject()->prefill([
			'test.street' => '123 Main St',
			'test.city' => 'Craigmore',
			'test.state' => 'SA',
			'test.postal_code' => '5112',
			'test.country' => 'Australia'
		]);
		$serialized = $field->serialize();
		$children = $serialized->children();

		$this->assertCount(5, $children);
		$this->assertSerializedChildrenContainsFieldWithNameOf('test.street', $children);
		$this->assertSerializedChildrenContainsFieldWithNameOf('test.city', $children);
		$this->assertSerializedChildrenContainsFieldWithNameOf('test.state', $children);
		$this->assertSerializedChildrenContainsFieldWithNameOf('test.postal_code', $children);
		$this->assertSerializedChildrenContainsFieldWithNameOf('test.country', $children);
	}
}
