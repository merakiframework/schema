<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\FieldName;
use Meraki\Schema\Field;
use Meraki\Schema\Field\Set;
use Meraki\Schema\Field\Text;
use InvalidArgumentException;
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
		$this->assertEquals([], $set->toArray());
		$this->assertTrue($set->isEmpty());
	}

	#[Test]
	public function fields_can_be_added(): void
	{
		$field1 = new Text(new FieldName('first'));

		$field2 = new Text(new FieldName('second'));

		$set = new Set($field1, $field2);

		$this->assertCount(2, $set);
		$this->assertSame([$field1, $field2], $set->toArray());
	}

	#[Test]
	public function a_duplicate_field_name_is_rejected(): void
	{
		// Previously the second field was silently discarded, which lost the definition
		// and gave no clue where it went.
		$field = new Text(new FieldName('first'));

		$set = new Set($field);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('A field named "first" already exists.');

		$set->add($field);
	}

	#[Test]
	public function a_different_field_sharing_a_name_is_also_rejected(): void
	{
		// Identity is the name, not the object: two distinct fields cannot share one.
		$first = new Text(new FieldName('email'));
		$second = new Text(new FieldName('email'));

		$set = new Set($first);

		$this->expectException(InvalidArgumentException::class);

		$set->add($second);
	}

	#[Test]
	public function add_returns_a_new_instance(): void
	{
		$field1 = new Text(new FieldName('one'));

		$field2 = new Text(new FieldName('two'));

		$set = new Set($field1);
		$newSet = $set->add($field2);

		$this->assertNotSame($set, $newSet);
		$this->assertCount(1, $set);
		$this->assertCount(2, $newSet);
	}

	#[Test]
	public function it_can_return_the_first_field(): void
	{
		$field = new Text(new FieldName('only'));

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
		$field = new Text(new FieldName('username'));

		$set = new Set($field);

		$this->assertSame($field, $set->findByName('username'));
		$this->assertSame($field, $set->findByName(new FieldName('username')));
	}

	#[Test]
	public function it_returns_null_if_field_name_not_found(): void
	{
		$field = new Text(new FieldName('username'));

		$set = new Set($field);

		$this->assertNull($set->findByName('email'));
	}

	#[Test]
	public function it_can_return_index_of_a_field(): void
	{
		$field1 = new Text(new FieldName('first'));

		$field2 = new Text(new FieldName('second'));

		$set = new Set($field1, $field2);

		$this->assertSame(1, $set->indexOf($field2));
	}

	#[Test]
	public function it_returns_null_for_index_if_field_not_found(): void
	{
		$field1 = new Text(new FieldName('first'));

		$field2 = new Text(new FieldName('second'));

		$set = new Set($field1);

		$this->assertNull($set->indexOf($field2));
	}

	#[Test]
	public function it_can_list_field_names(): void
	{
		$field1 = new Text(new FieldName('one'));

		$field2 = new Text(new FieldName('two'));

		$set = new Set($field1, $field2);

		$this->assertSame(['one', 'two'], $set->listFieldNames());
	}

	#[Test]
	public function it_supports_iteration(): void
	{
		$field1 = new Text(new FieldName('one'));

		$field2 = new Text(new FieldName('two'));

		$set = new Set($field1, $field2);

		$names = [];
		foreach ($set as $field) {
			$names[] = (string) $field->name;
		}

		$this->assertSame(['one', 'two'], $names);
	}
}
