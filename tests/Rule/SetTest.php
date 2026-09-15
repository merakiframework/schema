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

		$rules = $set->toArray();

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

	/**
	 * A set cannot be changed in place, which is what makes sharing one safe.
	 *
	 * These two replace tests that asserted the opposite — `rules_can_be_added_mutably` and its
	 * removal twin. The capability was real and was the hole in the central claim of 2.0:
	 * `Facade::copyForRequest()` hands every concurrent request the *same* set instance, on the
	 * grounds that every way of changing one returns a new set. A public `mutableAdd()` made that
	 * false, so one caller could change a definition every in-flight request was reading.
	 */
	#[Test]
	public function adding_a_rule_leaves_the_original_set_alone(): void
	{
		$set = new Set();
		$rule = $this->createRule();

		$grown = $set->add($rule);

		$this->assertCount(0, $set);
		$this->assertCount(1, $grown);
		$this->assertNotSame($set, $grown);
	}

	#[Test]
	public function removing_a_rule_leaves_the_original_set_alone(): void
	{
		$rule = $this->createRule();
		$set = new Set($rule);

		$shrunk = $set->remove($rule);

		$this->assertCount(1, $set);
		$this->assertCount(0, $shrunk);
		$this->assertFalse($shrunk->contains($rule));
	}

	/**
	 * Removal renumbers, so a set that has had something taken out is still a list.
	 *
	 * `unset()` left a hole, which is the same defect that made `getFailed()->getFirst()` return
	 * null on a result that plainly had failures.
	 */
	#[Test]
	public function removing_the_first_rule_leaves_the_rest_reachable(): void
	{
		$first = $this->createRule();
		$second = $this->createRule();

		$remaining = (new Set($first, $second))->remove($first);

		$this->assertSame($second, $remaining->first());
	}

	protected function createRule(): Rule
	{
		return $this->createMock(Rule::class);
	}
}
