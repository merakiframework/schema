<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Facade;
use Meraki\Schema\PropertyScope;
use Meraki\Schema\Rule\Outcome;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass, Group};

/**
 * A rule that targets something the schema does not have used to be accepted and then fail
 * on whichever user request first matched it — a typo in a scope reaching production as a
 * 500. It is now rejected where the rule is written.
 *
 * The trade is an ordering constraint that did not exist before: a rule can only be added
 * once the fields it names are. That is pinned here too, so the cost stays visible.
 */
#[Group('scope')]
#[CoversClass(Facade::class)]
final class ScopeValidationTest extends TestCase
{


	private function schema(): Facade
	{
		$schema = new Facade('signup');
		$schema->add($schema->createTextField('username')->minLengthOf(3));
		$schema->add($schema->createTextField('nickname')->makeOptional());

		return $schema;
	}

	#[Test]
	public function a_rule_naming_an_unknown_field_is_rejected_when_it_is_added(): void
	{
		$schema = $this->schema();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/usernmae/');

		$schema->addRule($schema->when('usernmae')->equals('admin')->thenRequire('nickname'));
	}

	#[Test]
	public function a_rule_naming_an_unknown_property_is_rejected_when_it_is_added(): void
	{
		$schema = $this->schema();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/nope/');

		$schema->addRule(
			$schema->when(PropertyScope::of('username', 'nope'))->equals(3)->thenRequire('nickname'),
		);
	}

	#[Test]
	public function an_outcome_pointed_at_a_value_is_rejected_when_it_is_built(): void
	{
		// This is the type error replacing the old runtime "can only be applied to fields".
		$this->expectException(InvalidArgumentException::class);

		new Outcome\MakeRequired('#/fields/username/value');
	}

	#[Test]
	public function a_rule_must_be_added_after_the_fields_it_names(): void
	{
		// The cost of checking early. Declaring rules first is no longer allowed.
		$schema = new Facade('signup');

		$this->expectException(InvalidArgumentException::class);

		$schema->addRule($schema->when('username')->equals('admin')->thenRequire('username'));
	}

	#[Test]
	public function a_rule_naming_only_real_targets_is_accepted(): void
	{
		$schema = $this->schema();

		$schema->addRule($schema->when('username')->equals('admin')->thenRequire('nickname'));

		$this->assertTrue($schema->validate((object)['username' => 'admin'])->anyFailed());
		$this->assertFalse($schema->validate((object)['username' => 'bob'])->anyFailed());
	}
}
