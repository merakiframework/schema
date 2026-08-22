<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Facade;
use Meraki\Schema\Scope;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('scope')]
#[CoversClass(Scope::class)]
final class ScopeTest extends TestCase
{
	#[Test]
	public function a_scope_cannot_step_into_a_fields_schema_back_reference(): void
	{
		// The back-reference points at the field's owner, so traversing it climbs back to
		// the root and walks the same path forever. Left unguarded this exhausts memory,
		// and rule targets are deserialised from untrusted documents by meraki/schema-json.
		$schema = new Facade('booking');
		$schema->addBooleanField('has_log_book');

		$this->expectException(InvalidArgumentException::class);

		(new Scope('#/fields/has_log_book/schema'))->resolve($schema);
	}

	#[Test]
	public function a_fields_public_configuration_stays_addressable(): void
	{
		// A field's public properties are its API; only the back-reference is excluded.
		$schema = new Facade('signup');
		$schema->addTextField('username')->minLengthOf(3)->maxLengthOf(20);

		$this->assertSame(3, (new Scope('#/fields/username/min'))->resolve($schema)->value);
		$this->assertSame(20, (new Scope('#/fields/username/max'))->resolve($schema)->value);
	}

	#[Test]
	public function optionality_is_addressable(): void
	{
		$schema = new Facade('signup');
		$schema->addTextField('nickname')->makeOptional();

		$this->assertTrue((new Scope('#/fields/nickname/optional'))->resolve($schema)->value);
	}

	#[Test]
	public function value_resolves_to_the_resolved_value(): void
	{
		$schema = new Facade('signup');
		$schema->addTextField('username')->prefill('default');

		$this->assertSame('default', (new Scope('#/fields/username/value'))->resolve($schema)->value->unwrap());

		$schema->input(['username' => 'given']);

		$this->assertSame('given', (new Scope('#/fields/username/value'))->resolve($schema)->value->unwrap());
	}

	#[Test]
	public function a_scope_pointing_at_a_field_resolves_to_the_field(): void
	{
		$schema = new Facade('signup');
		$field = $schema->addTextField('username');

		$this->assertSame($field, (new Scope('#/fields/username'))->resolve($schema)->value);
	}

	#[Test]
	public function an_unknown_property_is_rejected(): void
	{
		$schema = new Facade('signup');
		$schema->addTextField('username');

		$this->expectException(InvalidArgumentException::class);

		(new Scope('#/fields/username/nope'))->resolve($schema);
	}

	#[Test]
	public function an_unknown_field_is_rejected(): void
	{
		$schema = new Facade('signup');
		$schema->addTextField('username');

		$this->expectException(InvalidArgumentException::class);

		(new Scope('#/fields/nope/value'))->resolve($schema);
	}

	#[Test]
	public function a_scope_can_be_resolved_more_than_once(): void
	{
		// Rule outcomes build their scope once and resolve it on every validation run.
		$schema = new Facade('signup');
		$schema->addTextField('username')->minLengthOf(3);
		$scope = new Scope('#/fields/username/min');

		$this->assertSame(3, $scope->resolve($schema)->value);
		$this->assertSame(3, $scope->resolve($schema)->value);
		$this->assertSame(3, $scope->resolve($schema)->value);
	}
}
