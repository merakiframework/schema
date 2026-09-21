<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Facade;
use Meraki\Schema\Rule;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\Outcome;
use Meraki\Schema\FieldScope;
use Meraki\Schema\Scope;
use Meraki\Schema\ScopeResolver;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass, Group};

/**
 * A long-lived worker validates against the same schema thousands of times, so an outcome
 * has to survive being applied repeatedly and give the same answer every time.
 *
 * It did not. An outcome builds its scope once in its constructor, and resolving a scope
 * used to walk a cursor to the end of the path — so the second application started from an
 * exhausted cursor and threw. Scopes are immutable values now and there is no cursor, but
 * the guarantee is worth keeping pinned: it is the one a shared schema depends on.
 */
#[Group('rule')]
#[CoversClass(Scope::class)]
#[CoversClass(Rule::class)]
final class RepeatedApplicationTest extends TestCase
{


	#[Test]
	public function an_outcome_can_be_applied_more_than_once(): void
	{
		// The rule requires phone_number, which is omitted — so both runs must fail, and
		// fail the same way. A second run that threw, or quietly stopped applying the
		// outcome, would show up here.
		$schema = $this->createSchemaWithAFiringRule();
		$data = (object)['method' => 'phone'];

		$this->assertTrue($schema->validate($data)->anyFailed());
		$this->assertTrue($schema->validate($data)->anyFailed());
	}

	#[Test]
	public function repeated_validation_gives_the_same_answer(): void
	{
		$schema = $this->createSchemaWithAFiringRule();
		$data = (object)['method' => 'phone', 'phone_number' => '0411 222 333'];

		$this->assertFalse($schema->validate($data)->anyFailed());
		$this->assertFalse($schema->validate($data)->anyFailed());
	}

	#[Test]
	public function an_outcome_does_not_change_the_authored_definition(): void
	{
		// phone_number is authored optional and made required by the rule. That is true of
		// one request, not of the schema, so the definition must come back unchanged.
		$schema = $this->createSchemaWithAFiringRule();

		$schema->validate((object)['method' => 'phone']);

		$this->assertTrue($schema->fields->findByName('phone_number')->optional);
	}

	#[Test]
	public function resolving_a_scope_twice_gives_the_same_answer(): void
	{
		$schema = $this->createSchemaWithAFiringRule();
		$scope = FieldScope::of('phone_number');
		$resolver = new ScopeResolver($schema);

		$this->assertSame($resolver->resolve($scope), $resolver->resolve($scope));
	}

	private function createSchemaWithAFiringRule(): Facade
	{
		$schema = new Facade('test');
		$schema->add($schema->createEnumField('method', ['email', 'phone'])->defaultsTo('phone'));
		$schema->add($phoneNumber = $schema->createTextField('phone_number')->makeOptional());
		$schema->addRule(new Rule(
			new Condition\AllOf(new Condition\Equals('#/fields/method/value', 'phone')),
			[Outcome\Reconfigure::from($phoneNumber, $phoneNumber->makeRequired())],
		));

		return $schema;
	}
}
