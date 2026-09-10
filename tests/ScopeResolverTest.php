<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Field\Factory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * A rule condition asks what a field was given. That used to be answered by reading a
 * value staged onto the field, so a condition only worked once the request had been
 * written into the schema — which is what made a shared schema unsafe.
 *
 * These tests hold the replacement in place: the value comes from the request, the
 * definition is only read, and the two kinds of target keep the meanings they had.
 */
#[Group('long-lived')]
#[CoversClass(ScopeResolver::class)]
final class ScopeResolverTest extends TestCase
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
		$schema->add($this->fields->createTextField('nickname')->prefill('anonymous'));

		return $schema;
	}

	#[Test]
	public function a_value_comes_from_the_request(): void
	{
		$schema = $this->schema();

		$resolved = (new ScopeResolver($schema, ['username' => 'alice']))
			->resolve(ValueScope::of('username'));

		$this->assertSame('alice', $resolved->unwrap());
	}

	#[Test]
	public function a_value_absent_from_the_request_falls_back_to_the_authored_default(): void
	{
		$schema = $this->schema();

		$resolved = (new ScopeResolver($schema, []))
			->resolve(ValueScope::of('nickname'));

		$this->assertSame('anonymous', $resolved->unwrap());
	}

	#[Test]
	public function two_requests_resolve_the_same_scope_to_their_own_values(): void
	{
		$schema = $this->schema();
		$scope = ValueScope::of('username');

		$alice = (new ScopeResolver($schema, ['username' => 'alice']))->resolve($scope);
		$mallory = (new ScopeResolver($schema, ['username' => 'mallory']))->resolve($scope);

		$this->assertSame('alice', $alice->unwrap());
		$this->assertSame('mallory', $mallory->unwrap());
	}

	#[Test]
	public function a_definition_property_is_read_from_the_schema(): void
	{
		// Not everything a scope can address depends on the request: `min` is what the
		// author wrote, and no request changes it.
		$schema = $this->schema();

		$resolved = (new ScopeResolver($schema, ['username' => 'alice']))
			->resolve(PropertyScope::of('username', 'minLength'));

		$this->assertSame(3, $resolved);
	}

	#[Test]
	public function resolving_leaves_the_schema_untouched(): void
	{
		$schema = $this->schema();
		$before = serialize($schema);

		$resolver = new ScopeResolver($schema, ['username' => 'alice', 'nickname' => 'al']);
		$resolver->resolve(ValueScope::of('username'));
		$resolver->resolve(PropertyScope::of('username', 'minLength'));
		$resolver->resolve(ValueScope::of('nickname'));

		$this->assertSame($before, serialize($schema));
	}

	#[Test]
	public function a_field_scope_resolves_to_the_field_itself(): void
	{
		$schema = new Facade('signup');
		$field = $this->fields->createTextField('username');
		$schema->add($field);

		$resolved = (new ScopeResolver($schema))->resolve(FieldScope::of('username'));

		$this->assertSame($field, $resolved);
	}

	#[Test]
	public function optionality_is_addressable(): void
	{
		$schema = new Facade('signup');
		$schema->add($this->fields->createTextField('nickname')->makeOptional());

		$this->assertTrue((new ScopeResolver($schema))->resolve(PropertyScope::of('nickname', 'optional')));
	}

	#[Test]
	public function a_fields_public_configuration_stays_addressable(): void
	{
		// A field's public properties are its API; only the back-reference is excluded.
		$schema = new Facade('signup');
		$schema->add($this->fields->createTextField('username')->minLengthOf(3)->maxLengthOf(20));

		$resolver = new ScopeResolver($schema);

		$this->assertSame(3, $resolver->resolve(PropertyScope::of('username', 'minLength')));
		$this->assertSame(20, $resolver->resolve(PropertyScope::of('username', 'maxLength')));
	}

	#[Test]
	public function a_scope_cannot_step_into_a_fields_schema_back_reference(): void
	{
		// The back-reference points at the field's owner. When resolution was a walk, this
		// climbed to the root and followed the same path forever; the resolver reads a
		// name-keyed set and has no pointer to follow, so the guard is all that is left of
		// defect B8.
		$schema = new Facade('booking');
		$schema->add($this->fields->createBooleanField('has_log_book'));

		$this->expectException(InvalidArgumentException::class);

		(new ScopeResolver($schema))->resolve(PropertyScope::of('has_log_book', 'schema'));
	}

	#[Test]
	public function an_unknown_property_is_rejected(): void
	{
		$schema = new Facade('signup');
		$schema->add($this->fields->createTextField('username'));

		$this->expectException(InvalidArgumentException::class);

		(new ScopeResolver($schema))->resolve(PropertyScope::of('username', 'nope'));
	}

	#[Test]
	public function an_unknown_field_is_rejected(): void
	{
		$schema = new Facade('signup');
		$schema->add($this->fields->createTextField('username'));

		$this->expectException(InvalidArgumentException::class);

		(new ScopeResolver($schema))->resolve(ValueScope::of('nope'));
	}

	#[Test]
	public function a_scope_can_be_resolved_more_than_once(): void
	{
		// Rule outcomes build their scope once and resolve it on every validation run. When
		// a scope was a cursor the second pass started from an exhausted one and threw.
		$schema = new Facade('signup');
		$schema->add($this->fields->createTextField('username')->minLengthOf(3));

		$resolver = new ScopeResolver($schema);
		$scope = PropertyScope::of('username', 'minLength');

		$this->assertSame(3, $resolver->resolve($scope));
		$this->assertSame(3, $resolver->resolve($scope));
		$this->assertSame(3, $resolver->resolve($scope));
	}
}
