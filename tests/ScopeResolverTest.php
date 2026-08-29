<?php
declare(strict_types=1);

namespace Meraki\Schema;

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
	private function schema(): Facade
	{
		$schema = new Facade('signup');
		$schema->addTextField('username')->minLengthOf(3);
		$schema->addTextField('nickname')->prefill('anonymous');

		return $schema;
	}

	#[Test]
	public function a_value_comes_from_the_request(): void
	{
		$schema = $this->schema();

		$resolved = (new ScopeResolver($schema, ['username' => 'alice']))
			->resolve(new Scope('#/fields/username/value'));

		$this->assertSame('alice', $resolved->unwrap());
	}

	#[Test]
	public function a_value_absent_from_the_request_falls_back_to_the_authored_default(): void
	{
		$schema = $this->schema();

		$resolved = (new ScopeResolver($schema, []))
			->resolve(new Scope('#/fields/nickname/value'));

		$this->assertSame('anonymous', $resolved->unwrap());
	}

	#[Test]
	public function two_requests_resolve_the_same_scope_to_their_own_values(): void
	{
		$schema = $this->schema();
		$scope = new Scope('#/fields/username/value');

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
			->resolve(new Scope('#/fields/username/min'));

		$this->assertSame(3, $resolved);
	}

	#[Test]
	public function resolving_leaves_the_schema_untouched(): void
	{
		$schema = $this->schema();
		$before = serialize($schema);

		$resolver = new ScopeResolver($schema, ['username' => 'alice', 'nickname' => 'al']);
		$resolver->resolve(new Scope('#/fields/username/value'));
		$resolver->resolve(new Scope('#/fields/username/min'));
		$resolver->resolve(new Scope('#/fields/nickname/value'));

		$this->assertSame($before, serialize($schema));
	}
}
