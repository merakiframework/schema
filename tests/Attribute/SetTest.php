<?php
declare(strict_types=1);

namespace Meraki\Schema\Attribute;

use Meraki\Schema\Attribute;
use Meraki\Schema\Attribute\Set;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Set::class)]
final class SetTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$set = new Set(Set::ALLOW_ANY);

		$this->assertInstanceOf(Set::class, $set);
	}

	#[Test]
	public function it_creates_a_new_instance_with_attributes(): void
	{
		$optional = new Attribute\Optional();
		$min = new Attribute\Min(5);
		$max = new Attribute\Max(10);

		$set = new Set(Set::ALLOW_ANY, $optional, $min, $max);

		$this->assertCount(3, $set);
		$this->assertEquals(3, $set->count());
		$this->assertFalse($set->isEmpty());
		$this->assertTrue($set->contains($optional));
		$this->assertTrue($set->contains($min));
		$this->assertTrue($set->contains($max));
	}

	#[Test]
	public function it_creates_a_new_instance_with_no_attributes(): void
	{
		$set = new Set(Set::ALLOW_ANY);

		$this->assertCount(0, $set);
		$this->assertEquals(0, $set->count());
		$this->assertTrue($set->isEmpty());
	}

	#[Test]
	public function adding_attributes_increases_count(): void
	{
		$set = new Set(Set::ALLOW_ANY);
		$optional = new Attribute\Optional();

		$set = $set->add($optional);

		$this->assertCount(1, $set);
		$this->assertEquals(1, $set->count());
		$this->assertFalse($set->isEmpty());
	}

	#[Test]
	public function adding_attributes_does_not_mutate_original_set(): void
	{
		$setWithoutOptional = new Set(Set::ALLOW_ANY);
		$optional = new Attribute\Optional();

		$setWithOptional = $setWithoutOptional->add($optional);

		$this->assertCount(0, $setWithoutOptional);
		$this->assertEquals(0, $setWithoutOptional->count());
		$this->assertTrue($setWithoutOptional->isEmpty());

		$this->assertCount(1, $setWithOptional);
		$this->assertEquals(1, $setWithOptional->count());
		$this->assertFalse($setWithOptional->isEmpty());
	}

	#[Test]
	public function does_not_add_attribute_more_than_once(): void
	{
		$set = new Set(Set::ALLOW_ANY, new Attribute\Optional());

		$set = $set->add(new Attribute\Optional());

		$this->assertCount(1, $set);
		$this->assertEquals(1, $set->count());
		$this->assertFalse($set->isEmpty());
	}

	#[Test]
	public function added_attributes_are_in_set(): void
	{
		$optional = new Attribute\Optional();
		$set = new Set(Set::ALLOW_ANY, $optional);

		$this->assertTrue($set->contains($optional));
		$this->assertFalse($set->contains(new Attribute\Min(6)));
	}

	#[Test]
	public function removing_attributes_decreases_count(): void
	{
		$optional = new Attribute\Optional();
		$min = new Attribute\Min(5);
		$max = new Attribute\Max(10);

		$set = new Set(Set::ALLOW_ANY, $optional, $min, $max);

		$set = $set->remove($min);

		$this->assertCount(2, $set);
		$this->assertEquals(2, $set->count());
		$this->assertFalse($set->isEmpty());
	}

	#[Test]
	public function removing_attributes_does_not_mutate_original_set(): void
	{
		$optional = new Attribute\Optional();
		$min = new Attribute\Min(5);
		$max = new Attribute\Max(10);

		$set = new Set(Set::ALLOW_ANY, $optional, $min, $max);

		$setWithoutMin = $set->remove($min);

		$this->assertCount(3, $set);
		$this->assertEquals(3, $set->count());
		$this->assertFalse($set->isEmpty());

		$this->assertCount(2, $setWithoutMin);
		$this->assertEquals(2, $setWithoutMin->count());
		$this->assertFalse($setWithoutMin->isEmpty());
	}

	#[Test]
	public function does_nothing_when_trying_to_remove_attributes_not_in_set(): void
	{
		$optional = new Attribute\Optional();
		$min = new Attribute\Min(5);
		$max = new Attribute\Max(10);
		$set = new Set(Set::ALLOW_ANY, $optional, $min, $max);

		$set = $set->remove($min);
		$set = $set->remove($min);

		$this->assertCount(2, $set);
		$this->assertEquals(2, $set->count());
		$this->assertFalse($set->isEmpty());
	}

	#[Test]
	public function removed_attributes_are_not_in_set(): void
	{
		$optional = new Attribute\Optional();
		$min = new Attribute\Min(5);
		$max = new Attribute\Max(10);
		$set = new Set(Set::ALLOW_ANY, $optional, $min, $max);

		$set = $set->remove($min);

		$this->assertFalse($set->contains($min));
	}

	#[Test]
	public function can_find_attributes_by_name(): void
	{
		$optional = new Attribute\Optional();
		$min = new Attribute\Min(5);
		$max = new Attribute\Max(10);
		$set = new Set(Set::ALLOW_ANY, $optional, $min, $max);

		$found = $set->findByName('min');

		$this->assertEquals($min, $found);
	}

	#[Test]
	public function returns_nothing_if_finding_attribute_by_name_and_not_in_set(): void
	{
		$optional = new Attribute\Optional();
		$min = new Attribute\Min(5);
		$max = new Attribute\Max(10);
		$set = new Set(Set::ALLOW_ANY, $optional, $min, $max);

		$found = $set->findByName('foo');

		$this->assertNull($found);
	}

	#[Test]
	public function can_merge_two_sets(): void
	{
		$optional = new Attribute\Optional();
		$min = new Attribute\Min(5);
		$max = new Attribute\Max(10);
		$set1 = new Set(Set::ALLOW_ANY, $optional, $min);
		$set2 = new Set(Set::ALLOW_ANY, $max);

		$merged = $set1->merge($set2);

		$this->assertCount(3, $merged);
		$this->assertEquals(3, $merged->count());
		$this->assertFalse($merged->isEmpty());
		$this->assertTrue($merged->contains($optional));
		$this->assertTrue($merged->contains($min));
		$this->assertTrue($merged->contains($max));
	}

	#[Test]
	public function can_merge_two_sets_with_same_attributes(): void
	{
		$optional = new Attribute\Optional();
		$min = new Attribute\Min(5);
		$max = new Attribute\Max(10);
		$set1 = new Set(Set::ALLOW_ANY, $optional, $min, $max);
		$set2 = new Set(Set::ALLOW_ANY, $optional, $min, $max);

		$merged = $set1->merge($set2);

		$this->assertCount(3, $merged);
		$this->assertEquals(3, $merged->count());
		$this->assertFalse($merged->isEmpty());
		$this->assertTrue($merged->contains($optional));
		$this->assertTrue($merged->contains($min));
		$this->assertTrue($merged->contains($max));
	}

	#[Test]
	public function merging_sets_does_not_mutate_original_sets(): void
	{
		$optional = new Attribute\Optional();
		$min = new Attribute\Min(5);
		$max = new Attribute\Max(10);
		$set1 = new Set(Set::ALLOW_ANY, $optional, $min);
		$set2 = new Set(Set::ALLOW_ANY, $max);

		$merged = $set1->merge($set2);

		$this->assertCount(2, $set1);
		$this->assertEquals(2, $set1->count());
		$this->assertFalse($set1->isEmpty());
		$this->assertTrue($set1->contains($optional));
		$this->assertTrue($set1->contains($min));

		$this->assertCount(1, $set2);
		$this->assertEquals(1, $set2->count());
		$this->assertFalse($set2->isEmpty());
		$this->assertTrue($set2->contains($max));
	}
}
