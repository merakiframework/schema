<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Exception\DuplicateFieldName;
use Meraki\Schema\Exception\UnknownField;
use Meraki\Schema\FieldName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

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

	#[Test]
	public function a_field_can_be_removed_by_the_field_itself(): void
	{
		$field = new Text(new FieldName('one'));
		$set = new Set($field, new Text(new FieldName('two')));

		$this->assertSame(['two'], $set->remove($field)->listFieldNames());
	}

	#[Test]
	public function a_field_can_be_removed_by_name(): void
	{
		$set = new Set(new Text(new FieldName('one')), new Text(new FieldName('two')));

		$this->assertSame(['two'], $set->remove('one')->listFieldNames());
		$this->assertSame(['two'], $set->remove(new FieldName('one'))->listFieldNames());
	}

	/**
	 * Same reason `add()` and `replace()` copy: a schema hands every concurrent request the very
	 * same set, on the grounds that nothing changes one in place.
	 */
	#[Test]
	public function remove_returns_a_new_instance(): void
	{
		$set = new Set(new Text(new FieldName('one')), new Text(new FieldName('two')));

		$smaller = $set->remove('one');

		$this->assertNotSame($set, $smaller);
		$this->assertSame(['one', 'two'], $set->listFieldNames());
		$this->assertSame(['two'], $smaller->listFieldNames());
	}

	/**
	 * Rules are applied in order and a later one may read a field an earlier one changed, so what
	 * is left keeps the order it was added in rather than being rebuilt.
	 */
	#[Test]
	public function removing_from_the_middle_keeps_the_order_of_the_rest(): void
	{
		$set = new Set(
			new Text(new FieldName('one')),
			new Text(new FieldName('two')),
			new Text(new FieldName('three')),
		);

		$this->assertSame(['one', 'three'], $set->remove('two')->listFieldNames());
	}

	/**
	 * Refusing rather than no-oping, for the same reason `getByName()` and `replace()` do: a
	 * mistyped name that quietly removed nothing would leave the field on the schema with nothing
	 * to say so.
	 */
	#[Test]
	public function removing_a_field_that_was_never_added_is_refused(): void
	{
		$set = new Set(new Text(new FieldName('one')));

		$this->expectException(UnknownField::class);
		$this->expectExceptionMessage('No field named "nope" to remove.');

		$set->remove('nope');
	}

	#[Test]
	public function removing_the_only_field_empties_the_set(): void
	{
		$set = new Set(new Text(new FieldName('one')));

		$this->assertTrue($set->remove('one')->isEmpty());
	}

	/**
	 * Two names that differ only by case cannot share a schema.
	 *
	 * `FieldName::equals()` is exact, because a name is a wire key and every lookup that reads
	 * one is exact. This is the guarantee that used to ride on equality folding case, kept and
	 * asked where it belongs: once, when the schema is written, rather than on every comparison
	 * in the library.
	 *
	 * What it prevents is a reader having to guess. A message or a scope path naming `email` on
	 * a schema that also holds `Email` says nothing about which one it meant.
	 */
	#[Test]
	public function two_names_that_differ_only_by_case_cannot_share_a_schema(): void
	{
		$this->expectException(DuplicateFieldName::class);
		$this->expectExceptionMessage('A field named "Email" already exists.');

		new Set(new Text(new FieldName('email')), new Text(new FieldName('Email')));
	}

	#[Test]
	public function names_that_differ_by_more_than_case_may_share_a_schema(): void
	{
		// The guard is about ambiguity, not about similarity: `email` and `e_mail` are two names
		// a reader can tell apart, so nothing here objects to them.
		$set = new Set(new Text(new FieldName('email')), new Text(new FieldName('e_mail')));

		$this->assertCount(2, $set);
	}
}
