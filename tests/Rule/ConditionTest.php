<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Rule\Condition;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

#[CoversClass(Condition::class)]
final class ConditionTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$this->assertTrue(class_exists(Condition::class));
	}

	#[Test]
	public function it_has_a_target(): void
	{
		$scope = '#/fields/contact_method/value';
		$condition = Condition::create($scope, 'equals', 'phone');

		$this->assertEquals($scope, $condition->target->value);
	}

	#[Test]
	public function it_has_an_operator(): void
	{
		$operator = 'equals';
		$condition = Condition::create('#/fields/contact_method/value', $operator, 'phone');

		$this->assertEquals($operator, $condition->operator->value);
	}

	#[Test]
	public function it_has_an_expected_value(): void
	{
		$value = 'phone';
		$condition = Condition::create('#/fields/contact_method/value', 'equals', $value);

		$this->assertEquals($value, $condition->expected->value);
	}

	#[Test]
	public function the_expected_value_can_be_optional(): void
	{
		$this->markTestSkipped('Not implemented yet.');
	}
}
