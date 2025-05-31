<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Property\Name;
use Meraki\Schema\Field;
use Meraki\Schema\Field\Set;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group('field')]
#[CoversClass(Set::class)]
final class SetTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$set = new Set();

		$this->assertInstanceOf(Set::class, $set);
	}

	#[Test]
	public function a_new_instance_is_empty(): void
	{
		$set = new Set();

		$this->assertCount(0, $set);
		$this->assertEmpty($set);
		$this->assertEquals([], $set->__toArray());
		$this->assertTrue($set->isEmpty());
	}

	#[Test]
	public function fields_can_be_added(): void
	{
		$field1 = $this->createStub(Field::class);
		$field1->name = new Name('first');

		$field2 = $this->createStub(Field::class);
		$field2->name = new Name('second');

		$set = new Set($field1, $field2);

		$this->assertCount(2, $set);
		$this->assertSame([$field1, $field2], $set->__toArray());
	}

	#[Test]
	public function duplicate_fields_are_not_added(): void
	{
		$field = $this->createStub(Field::class);
		$field->name = new Name('first');

		$set = new Set($field);
		$set->mutableAdd($field); // should not add again

		$this->assertCount(1, $set);
	}

	#[Test]
	public function add_returns_a_new_instance(): void
	{
		$field1 = $this->createStub(Field::class);
		$field1->name = new Name('one');

		$field2 = $this->createStub(Field::class);
		$field2->name = new Name('two');

		$set = new Set($field1);
		$newSet = $set->add($field2);

		$this->assertNotSame($set, $newSet);
		$this->assertCount(1, $set);
		$this->assertCount(2, $newSet);
	}

	#[Test]
	public function it_can_return_the_first_field(): void
	{
		$field = $this->createStub(Field::class);
		$field->name = new Name('only');

		$set = new Set($field);

		$this->assertSame($field, $set->first());
	}

	#[Test]
	public function it_returns_null_when_first_field_is_not_available(): void
	{
		$set = new Set();

		$this->assertNull($set->first());
	}

	#[Test]
	public function it_can_find_field_by_name(): void
	{
		$field = $this->createStub(Field::class);
		$field->name = new Name('username');

		$set = new Set($field);

		$this->assertSame($field, $set->findByName('username'));
		$this->assertSame($field, $set->findByName(new Name('username')));
	}

	#[Test]
	public function it_returns_null_if_field_name_not_found(): void
	{
		$field = $this->createStub(Field::class);
		$field->name = new Name('username');

		$set = new Set($field);

		$this->assertNull($set->findByName('email'));
	}

	#[Test]
	public function it_can_return_index_of_a_field(): void
	{
		$field1 = $this->createStub(Field::class);
		$field1->name = new Name('first');

		$field2 = $this->createStub(Field::class);
		$field2->name = new Name('second');

		$set = new Set($field1, $field2);

		$this->assertSame(1, $set->indexOf($field2));
	}

	#[Test]
	public function it_returns_null_for_index_if_field_not_found(): void
	{
		$field1 = $this->createStub(Field::class);
		$field1->name = new Name('first');

		$field2 = $this->createStub(Field::class);
		$field2->name = new Name('second');

		$set = new Set($field1);

		$this->assertNull($set->indexOf($field2));
	}

	#[Test]
	public function it_can_prefix_field_names(): void
	{
		$field = new Field\Text(new Name('bar'));
		$set = new Set($field);

		$set->prefixNamesWith(new Name('foo'));

		$this->assertSame($field, $set->findByName('foo.bar'));
	}

	#[Test]
	public function it_can_list_field_names(): void
	{
		$field1 = $this->createStub(Field::class);
		$field1->name = new Name('one');

		$field2 = $this->createStub(Field::class);
		$field2->name = new Name('two');

		$set = new Set($field1, $field2);

		$this->assertSame(['one', 'two'], $set->listFieldNames());
	}

	#[Test]
	public function it_supports_iteration(): void
	{
		$field1 = $this->createStub(Field::class);
		$field1->name = new Name('one');

		$field2 = $this->createStub(Field::class);
		$field2->name = new Name('two');

		$set = new Set($field1, $field2);

		$names = [];
		foreach ($set as $field) {
			$names[] = (string) $field->name;
		}

		$this->assertSame(['one', 'two'], $names);
	}
}
