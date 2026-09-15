<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Facade;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A rule under construction forks, like every other fluent call in this library.
 *
 * `then()` and `otherwise()` used to write to `$this` and return it, which made two fluent idioms
 * with opposite meanings sit side by side: a field wither hands back a copy and leaves the original
 * alone, and a draft did not. Nothing in the signatures told them apart.
 *
 * It broke the thing {@see Facade::allOf()} actively invites — holding a condition in a variable and
 * building more than one rule from it. Both rules came out as the *same object* carrying both sets
 * of outcomes, so requiring one field also required the other.
 */
#[Group('rule')]
#[CoversClass(Draft::class)]
final class DraftTest extends TestCase
{
	private function schema(): Facade
	{
		$schema = new Facade('drafts');
		$schema->add(
			$schema->createTextField('plan'),
			$schema->createTextField('first'),
			$schema->createTextField('second'),
		);

		return $schema;
	}

	#[Test]
	public function a_condition_can_be_reused_for_two_rules(): void
	{
		$schema = $this->schema();
		$base = $schema->when('plan')->equals('pro');

		$requiresFirst = $base->thenRequire('first');
		$requiresSecond = $base->thenRequire('second');

		$this->assertNotSame($requiresFirst, $requiresSecond);
		$this->assertCount(1, $requiresFirst->build()->outcomes);
		$this->assertCount(1, $requiresSecond->build()->outcomes);
	}

	#[Test]
	public function attaching_an_outcome_leaves_the_draft_it_came_from_bare(): void
	{
		$base = $this->schema()->when('plan')->equals('pro');

		$base->thenRequire('first');

		$this->assertFalse(
			$base->hasOutcomes(),
			'The draft was changed in place, so a condition cannot be reused.',
		);
	}

	#[Test]
	public function the_else_branch_forks_too(): void
	{
		$base = $this->schema()->when('plan')->equals('pro');

		$one = $base->otherwiseMakeOptional('first');
		$two = $base->otherwiseMakeOptional('second');

		$this->assertNotSame($one, $two);
		$this->assertCount(1, $one->build()->otherwise);
		$this->assertCount(1, $two->build()->otherwise);
	}

	/**
	 * Chaining still accumulates, which is what makes the copy invisible in ordinary use.
	 */
	#[Test]
	public function chaining_on_one_draft_still_collects_every_outcome(): void
	{
		$rule = $this->schema()
			->when('plan')->equals('pro')
			->thenRequire('first')
			->thenRequire('second')
			->otherwiseMakeOptional('first')
			->build();

		$this->assertCount(2, $rule->outcomes);
		$this->assertCount(1, $rule->otherwise);
	}

	/**
	 * Two rules built from one condition really do behave independently once added.
	 */
	#[Test]
	public function two_rules_from_one_condition_act_only_on_their_own_field(): void
	{
		$schema = $this->schema();
		$base = $schema->when('plan')->equals('pro');

		$schema->addRule($base->thenRequire('first'));

		$result = $schema->validate((object) ['plan' => 'pro']);

		$this->assertTrue($result->forField('first')->wasAlteredByRule());
		$this->assertFalse(
			$result->forField('second')->wasAlteredByRule(),
			'An outcome attached to a different draft leaked into this rule.',
		);
	}

	#[Test]
	public function a_draft_with_no_outcome_refuses_to_become_a_rule(): void
	{
		$this->expectException(LogicException::class);

		$this->schema()->when('plan')->equals('pro')->build();
	}
}
