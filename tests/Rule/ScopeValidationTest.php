<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Field\Factory;
use Meraki\Schema\Facade;
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
	private Factory $fields;

	protected function setUp(): void
	{
		$this->fields = new Factory();
	}

	private function schema(): Facade
	{
		$schema = new Facade('signup');
		$schema->add($this->fields->createTextField('username')->minLengthOf(3));
		$schema->add($this->fields->createTextField('nickname')->makeOptional());

		return $schema;
	}

	#[Test]
	public function a_rule_naming_an_unknown_field_is_rejected_when_it_is_added(): void
	{
		$schema = $this->schema();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/usernmae/');

		$schema->whenAllMatch(
			fn($rule) => $rule
				->whenEquals('#/fields/usernmae/value', 'admin')
				->thenRequire('#/fields/nickname'),
		);
	}

	#[Test]
	public function a_rule_naming_an_unknown_property_is_rejected_when_it_is_added(): void
	{
		$schema = $this->schema();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/nope/');

		$schema->whenAllMatch(
			fn($rule) => $rule
				->whenEquals('#/fields/username/nope', 3)
				->thenRequire('#/fields/nickname'),
		);
	}

	#[Test]
	public function an_outcome_pointed_at_a_value_is_rejected_when_it_is_built(): void
	{
		// This is the type error replacing the old runtime "can only be applied to fields".
		$this->expectException(InvalidArgumentException::class);

		new Outcome\_Require('#/fields/username/value');
	}

	#[Test]
	public function a_rule_must_be_added_after_the_fields_it_names(): void
	{
		// The cost of checking early. Declaring rules first is no longer allowed.
		$schema = new Facade('signup');

		$this->expectException(InvalidArgumentException::class);

		$schema->whenAllMatch(
			fn($rule) => $rule
				->whenEquals('#/fields/username/value', 'admin')
				->thenRequire('#/fields/username'),
		);
	}

	#[Test]
	public function a_rule_naming_only_real_targets_is_accepted(): void
	{
		$schema = $this->schema();

		$schema->whenAllMatch(
			fn($rule) => $rule
				->whenEquals('#/fields/username/value', 'admin')
				->thenRequire('#/fields/nickname'),
		);

		$this->assertTrue($schema->validate(['username' => 'admin'])->anyFailed());
		$this->assertFalse($schema->validate(['username' => 'bob'])->anyFailed());
	}
}
