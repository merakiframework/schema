<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Rule\ConditionGroup;
use Meraki\Schema\Rule\Condition;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

#[CoversClass(ConditionGroup::class)]
final class ConditionGroupTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$this->assertTrue(class_exists(ConditionGroup::class));
	}

	#[Test]
	public function it_is_a_condition(): void
	{
		$conditions = ConditionGroup::allOf();

		$this->assertInstanceOf(Condition::class, $conditions);
	}

	#[Test]
	public function it_has_no_conditions_by_default(): void
	{
		$conditions = ConditionGroup::allOf();

		$this->assertCount(0, $conditions);
	}

	#[Test]
	public function it_can_have_conditions_added(): void
	{
		$equals = Condition::create('#/fields/contact_method/value', 'equals', 'phone');
		$conditions = ConditionGroup::allOf();

		$conditions = $conditions->add($equals);

		$this->assertCount(1, $conditions);
	}

	#[Test]
	public function conditions_can_be_added_during_construction(): void
	{
		$equals = Condition::create('#/fields/contact_method/value', 'equals', 'phone');
		$conditions = ConditionGroup::allOf($equals);

		$this->assertCount(1, $conditions);
	}

	#[Test]
	public function groups_are_not_counted_as_conditions(): void
	{
		$equals = Condition::create('#/fields/contact_method/value', 'equals', 'phone');
		$notEquals = Condition::create('#/fields/contact_method/value', 'not equals', 'email');
		$group = ConditionGroup::anyOf($notEquals);
		$conditions = ConditionGroup::allOf($equals, $group);

		$this->assertCount(2, $conditions);
	}
}
