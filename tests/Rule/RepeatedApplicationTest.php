<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Facade;
use Meraki\Schema\Rule;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\Outcome;
use Meraki\Schema\Scope;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass, Group};

/**
 * Rules get applied more than once in ordinary use — `input()` applies them, and so does
 * `validate()` — so an outcome has to survive being applied repeatedly.
 *
 * It did not: an outcome builds its {@see Scope} once in its constructor, and resolving a
 * scope walks a cursor to the end of the path, so the second application started from an
 * exhausted cursor and threw.
 */
#[Group('rule')]
#[CoversClass(Scope::class)]
#[CoversClass(Rule::class)]
final class RepeatedApplicationTest extends TestCase
{
	#[Test]
	public function an_outcome_can_be_applied_more_than_once(): void
	{
		$schema = $this->createSchemaWithAFiringRule();

		$schema->applyRules();
		$schema->applyRules();

		$this->assertFalse($schema->fields->findByName('phone_number')->optional);
	}

	#[Test]
	public function inputting_then_validating_applies_the_rules_twice(): void
	{
		$schema = $this->createSchemaWithAFiringRule();
		$data = ['method' => 'phone', 'phone_number' => '0411 222 333'];

		$schema->input($data);
		$result = $schema->validate($data);

		$this->assertFalse($result->anyFailed());
	}

	#[Test]
	public function resolving_a_scope_twice_gives_the_same_answer(): void
	{
		$schema = $this->createSchemaWithAFiringRule();
		$scope = new Scope('#/fields/phone_number');

		$this->assertSame($scope->resolve($schema)->value, $scope->resolve($schema)->value);
	}

	private function createSchemaWithAFiringRule(): Facade
	{
		$schema = new Facade('test');
		$schema->addEnumField('method', ['email', 'phone'])->prefill('phone');
		$schema->addTextField('phone_number')->makeOptional();
		$schema->addRule(new Rule(
			new Condition\AllOf(new Condition\Equals('#/fields/method/value', 'phone')),
			[new Outcome\_Require('#/fields/phone_number')],
		));

		return $schema;
	}
}
