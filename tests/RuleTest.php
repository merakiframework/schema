<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Rule;
use Meraki\Schema\Rule\ConditionGroup;
use Meraki\Schema\Rule\OutcomeGroup;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass};

#[CoversClass(Rule::class)]
final class RuleTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$rule = Rule::matchAll();

		$this->assertInstanceOf(Rule::class, $rule);
	}

	#[Test]
	public function it_has_conditions(): void
	{
		$rule = Rule::matchAll();

		$this->assertInstanceOf(ConditionGroup::class, $rule->conditions);
	}

	#[Test]
	public function it_has_outcomes(): void
	{
		$rule = Rule::matchAll();

		$this->assertInstanceOf(OutcomeGroup::class, $rule->outcomes);
	}

	#[Test]
	public function can_be_created_to_match_all_conditions(): void
	{
		$rule = Rule::matchAll();

		$this->assertTrue($rule->conditions->mustMatchAll());
	}

	#[Test]
	public function can_be_created_to_match_any_conditions(): void
	{
		$rule = Rule::matchAny();

		$this->assertTrue($rule->conditions->mustMatchSome());
	}

	#[Test]
	public function can_be_created_to_match_no_conditions(): void
	{
		$rule = Rule::matchNone();

		$this->assertTrue($rule->conditions->cannotMatchAny());
	}
}
