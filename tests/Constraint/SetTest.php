<?php
declare(strict_types=1);

namespace Meraki\Form\Constraint;

use Meraki\Form\Constraint;
use Meraki\Form\Constraint\Set;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

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
	public function it_creates_a_new_instance_with_constraints(): void
	{
		$required = new Constraint\Required();
		$min = new Constraint\Min(5);
		$max = new Constraint\Max(10);

		$set = new Set($required, $min, $max);

		$this->assertCount(3, $set);
		$this->assertEquals(3, $set->count());
		$this->assertFalse($set->isEmpty());
		$this->assertTrue($set->contains($required));
		$this->assertTrue($set->contains($min));
		$this->assertTrue($set->contains($max));
	}

	#[Test]
	public function it_creates_a_new_instance_with_no_constraints(): void
	{
		$set = new Set();

		$this->assertCount(0, $set);
		$this->assertEquals(0, $set->count());
		$this->assertTrue($set->isEmpty());
	}

	#[Test]
	public function adding_constraint_increases_count(): void
	{
		$set = new Set();
		$required = new Constraint\Required();

		$set = $set->add($required);

		$this->assertCount(1, $set);
		$this->assertEquals(1, $set->count());
		$this->assertFalse($set->isEmpty());
	}

	#[Test]
	public function adding_constraint_does_not_mutate_original_set(): void
	{
		$set = new Set();
		$required = new Constraint\Required();

		$setWithRequired = $set->add($required);

		$this->assertCount(0, $set);
		$this->assertEquals(0, $set->count());
		$this->assertTrue($set->isEmpty());

		$this->assertCount(1, $setWithRequired);
		$this->assertEquals(1, $setWithRequired->count());
		$this->assertFalse($setWithRequired->isEmpty());
	}

	#[Test]
	public function adding_constraint_that_is_already_in_set_is_idempotent(): void
	{
		$set = new Set();
		$required = new Constraint\Required();

		$set = $set->add($required);
		$set = $set->add($required);

		$this->assertCount(1, $set);
		$this->assertEquals(1, $set->count());
		$this->assertFalse($set->isEmpty());
	}

	#[Test]
	public function added_constraint_is_contained_in_the_set(): void
	{
		$set = new Set();
		$required = new Constraint\Required();

		$set = $set->add($required);

		$this->assertTrue($set->contains($required));
	}

	#[Test]
	public function removing_constraint_decreases_count(): void
	{
		$required = new Constraint\Required();
		$min = new Constraint\Min(5);
		$max = new Constraint\Max(10);

		$set = new Set($required, $min, $max);

		$set = $set->remove($min);

		$this->assertCount(2, $set);
		$this->assertEquals(2, $set->count());
		$this->assertFalse($set->isEmpty());
	}

	#[Test]
	public function removing_constraint_does_not_mutate_original_set(): void
	{
		$required = new Constraint\Required();
		$min = new Constraint\Min(5);
		$max = new Constraint\Max(10);

		$set = new Set($required, $min, $max);

		$setWithoutMin = $set->remove($min);

		$this->assertCount(3, $set);
		$this->assertEquals(3, $set->count());
		$this->assertFalse($set->isEmpty());

		$this->assertCount(2, $setWithoutMin);
		$this->assertEquals(2, $setWithoutMin->count());
		$this->assertFalse($setWithoutMin->isEmpty());
	}

	#[Test]
	public function removing_constraint_that_is_not_in_set_is_idempotent(): void
	{
		$required = new Constraint\Required();
		$min = new Constraint\Min(5);
		$max = new Constraint\Max(10);
		$set = new Set($required, $min, $max);

		$set = $set->remove($min);
		$set = $set->remove($min);

		$this->assertCount(2, $set);
		$this->assertEquals(2, $set->count());
		$this->assertFalse($set->isEmpty());
	}

	#[Test]
	public function removed_constraint_is_not_contained_in_the_set(): void
	{
		$required = new Constraint\Required();
		$min = new Constraint\Min(5);
		$max = new Constraint\Max(10);
		$set = new Set($required, $min, $max);

		$set = $set->remove($min);

		$this->assertFalse($set->contains($min));
	}

	#[Test]
	public function replacing_constraint_in_set_does_not_mutate_original_set(): void
	{
		$required = new Constraint\Required();
		$min = new Constraint\Min(5);
		$max = new Constraint\Max(10);
		$set = new Set($required, $min, $max);

		$min = new Constraint\Min(3);
		$setWithReplacedMin = $set->replace($min);

		$this->assertCount(3, $set);
		$this->assertEquals(3, $set->count());
		$this->assertFalse($set->isEmpty());
		$this->assertTrue($set->findByName('min')->hasValueOf(5));

		$this->assertCount(3, $setWithReplacedMin);
		$this->assertEquals(3, $setWithReplacedMin->count());
		$this->assertFalse($setWithReplacedMin->isEmpty());
		$this->assertTrue($setWithReplacedMin->findByName('min')->hasValueOf(3));
	}

	#[Test]
	public function can_get_constraint_by_name(): void
	{
		$required = new Constraint\Required();
		$min = new Constraint\Min(5);
		$max = new Constraint\Max(10);
		$set = new Set($required, $min, $max);

		$found = $set->findByName('min');

		$this->assertEquals($min, $found);
	}

	#[Test]
	public function can_get_constraint_by_name_if_not_exists(): void
	{
		$required = new Constraint\Required();
		$min = new Constraint\Min(5);
		$max = new Constraint\Max(10);
		$set = new Set($required, $min, $max);

		$found = $set->findByName('foo');

		$this->assertNull($found);
	}

	#[Test]
	public function can_merge_two_sets(): void
	{
		$required = new Constraint\Required();
		$min = new Constraint\Min(5);
		$max = new Constraint\Max(10);
		$set1 = new Set($required, $min);
		$set2 = new Set($max);

		$merged = $set1->merge($set2);

		$this->assertCount(3, $merged);
		$this->assertEquals(3, $merged->count());
		$this->assertFalse($merged->isEmpty());
		$this->assertTrue($merged->contains($required));
		$this->assertTrue($merged->contains($min));
		$this->assertTrue($merged->contains($max));
	}

	#[Test]
	public function can_merge_two_sets_with_same_constraints(): void
	{
		$required = new Constraint\Required();
		$min = new Constraint\Min(5);
		$max = new Constraint\Max(10);
		$set1 = new Set($required, $min, $max);
		$set2 = new Set($required, $min, $max);

		$merged = $set1->merge($set2);

		$this->assertCount(3, $merged);
		$this->assertEquals(3, $merged->count());
		$this->assertFalse($merged->isEmpty());
		$this->assertTrue($merged->contains($required));
		$this->assertTrue($merged->contains($min));
		$this->assertTrue($merged->contains($max));
	}
}
