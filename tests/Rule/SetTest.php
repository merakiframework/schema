<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Rule;
use Meraki\Schema\Rule\Set;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

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
	public function is_empty_by_default(): void
	{
		$set = new Set();

		$this->assertCount(0, $set);
		$this->assertTrue($set->isEmpty());
	}

	#[Test]
	public function can_be_created_with_rules(): void
	{
		$rule1 = $this->createRule();
		$rule2 = $this->createRule();

		$set = new Set($rule1, $rule2);

		$this->assertCount(2, $set);
		$this->assertFalse($set->isEmpty());
		$this->assertTrue($set->contains($rule1));
		$this->assertTrue($set->contains($rule2));
	}

	#[Test]
	public function adding_a_rule_increases_the_count(): void
	{
		$set = new Set();
		$rule = $this->createRule();

		$set = $set->add($rule);

		$this->assertCount(1, $set);
		$this->assertFalse($set->isEmpty());
	}

	#[Test]
	public function removing_a_rule_decreases_the_count(): void
	{
		$rule = $this->createRule();
		$set = new Set($rule);

		$set = $set->remove($rule);

		$this->assertCount(0, $set);
		$this->assertTrue($set->isEmpty());
	}

	#[Test]
	public function adding_a_rule_is_contained_in_set(): void
	{
		$set = new Set();
		$rule = $this->createRule();

		$set = $set->add($rule);

		$this->assertTrue($set->contains($rule));
	}

	#[Test]
	public function removing_a_rule_is_not_contained_in_set(): void
	{
		$rule = $this->createRule();
		$set = new Set($rule);

		$set = $set->remove($rule);

		$this->assertFalse($set->contains($rule));
	}

	#[Test]
	public function rules_can_be_iterated_over(): void
	{
		$rule1 = $this->createRule();
		$rule2 = $this->createRule();
		$set = new Set($rule1, $rule2);

		$rules = iterator_to_array($set);

		$this->assertCount(2, $rules);
		$this->assertContains($rule1, $rules);
		$this->assertContains($rule2, $rules);
	}

	#[Test]
	public function rules_can_be_converted_to_array(): void
	{
		$rule1 = $this->createRule();
		$rule2 = $this->createRule();
		$set = new Set($rule1, $rule2);

		$rules = $set->__toArray();

		$this->assertCount(2, $rules);
		$this->assertContains($rule1, $rules);
		$this->assertContains($rule2, $rules);
	}

	#[Test]
	public function adding_a_rule_is_immutable(): void
	{
		$set = new Set();
		$rule = $this->createRule();

		$newSet = $set->add($rule);

		$this->assertNotSame($set, $newSet);
		$this->assertCount(0, $set);
		$this->assertCount(1, $newSet);
		$this->assertTrue($newSet->contains($rule));
	}

	#[Test]
	public function removing_a_rule_is_immutable(): void
	{
		$rule = $this->createRule();
		$set = new Set($rule);

		$newSet = $set->remove($rule);

		$this->assertNotSame($set, $newSet);
		$this->assertCount(1, $set);
		$this->assertCount(0, $newSet);
		$this->assertFalse($newSet->contains($rule));
	}

	#[Test]
	public function rules_can_be_added_mutably(): void
	{
		$set = new Set();
		$rule = $this->createRule();

		$set->mutableAdd($rule);

		$this->assertCount(1, $set);
		$this->assertTrue($set->contains($rule));
	}

	#[Test]
	public function rules_can_be_removed_mutably(): void
	{
		$rule = $this->createRule();
		$set = new Set($rule);

		$set->mutableRemove($rule);

		$this->assertCount(0, $set);
		$this->assertFalse($set->contains($rule));
	}

	protected function createRule(): Rule
	{
		return $this->createMock(Rule::class);
	}
}
