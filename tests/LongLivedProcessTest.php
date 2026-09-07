<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Fiber;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * A schema is meant to be built once when a worker boots and reused for the life of the
 * process — Swoole, RoadRunner, FrankenPHP. That only holds if validating writes nothing
 * back to the schema.
 *
 * Each test here pins down one claim made in docs/LIMITATIONS.md#b7. They are the
 * acceptance criteria for that defect: B7 is closed only while all five pass, so a change
 * that reintroduces per-request state on a field should fail here first.
 *
 * Fibers stand in for coroutines. They are part of the language, so this runs everywhere
 * the suite does and needs no extension to reproduce the interleaving that matters.
 */
#[Group('long-lived')]
#[CoversClass(Facade::class)]
final class LongLivedProcessTest extends TestCase
{
	/**
	 * Built once per test, exactly as a worker would build it once at boot.
	 *
	 * The rule matters: rules work by changing fields, so a schema carrying one exercises
	 * the path most likely to write back to the definition.
	 */
	private function bootSchema(): Facade
	{
		$schema = new Facade('signup');
		$schema->addTextField('username')->minLengthOf(3);
		$schema->addTextField('nickname')->makeOptional();
		$schema->whenAllMatch(
			fn($rule) => $rule
				->whenEquals('#/fields/username/value', 'admin')
				->thenRequire('#/fields/nickname'),
		);

		return $schema;
	}

	#[Test]
	public function two_requests_interleaved_mid_validation_do_not_see_each_others_data(): void
	{
		$schema = $this->bootSchema();

		$request = static fn(string $username): Fiber => new Fiber(
			static function () use ($schema, $username): mixed {
				$result = $schema->validate(['username' => $username]);

				// Whatever the handler does next — a query, an HTTP call — is where a
				// coroutine yields and its neighbour runs.
				Fiber::suspend();

				return $result->get('username')->value;
			},
		);

		$alice = $request('alice');
		$mallory = $request('mallory');

		$alice->start();
		$mallory->start();
		$alice->resume();
		$mallory->resume();

		$this->assertSame('alice', $alice->getReturn());
		$this->assertSame('mallory', $mallory->getReturn());
	}

	#[Test]
	public function validating_a_clone_leaves_the_original_untouched(): void
	{
		// Neither Facade nor Field\Set defines __clone, so a clone shares the very same
		// Field objects. That used to make the obvious workaround fail silently; it is
		// safe now only because validation writes nothing to a field.
		$schema = $this->bootSchema();
		$before = serialize($schema);

		$clone = clone $schema;
		$clone->validate(['username' => 'admin', 'nickname' => 'root']);

		$this->assertSame($before, serialize($schema));
	}

	#[Test]
	public function submitted_data_is_not_retained_after_the_request(): void
	{
		// A worker lives for days. Anything a field keeps hold of is user data sitting in
		// memory long after the request that supplied it has gone.
		$schema = $this->bootSchema();

		$schema->validate([
			'username' => 'alice-must-not-persist',
			'nickname' => 'nickname-must-not-persist',
		]);

		$retained = serialize($schema);

		$this->assertStringNotContainsString('alice-must-not-persist', $retained);
		$this->assertStringNotContainsString('nickname-must-not-persist', $retained);
	}

	#[Test]
	public function validating_does_not_change_the_schema(): void
	{
		// The broadest of the five: whatever else validation does, the definition it ran
		// against must come out identical, whether a rule matched or not.
		$schema = $this->bootSchema();
		$before = serialize($schema);

		$schema->validate(['username' => 'admin']);                       // rule matches
		$schema->validate(['username' => 'bob', 'nickname' => 'bobby']);   // rule does not
		$schema->resolve(['username' => 'carol']);

		$this->assertSame($before, serialize($schema));
	}

	#[Test]
	public function repeated_validation_is_order_independent(): void
	{
		// RoadRunner's model: one request at a time, but thousands of them against the
		// same instance. An answer must not depend on what the worker saw before it.
		$adminWithoutNickname = ['username' => 'admin'];                        // fails
		$bobWithNickname = ['username' => 'bob', 'nickname' => 'bobby'];        // passes

		$forwards = $this->bootSchema();
		$this->assertTrue($forwards->validate($adminWithoutNickname)->anyFailed());
		$this->assertFalse($forwards->validate($bobWithNickname)->anyFailed());

		$backwards = $this->bootSchema();
		$this->assertFalse($backwards->validate($bobWithNickname)->anyFailed());
		$this->assertTrue($backwards->validate($adminWithoutNickname)->anyFailed());

		$reused = $this->bootSchema();

		for ($i = 0; $i < 3; $i++) {
			$this->assertTrue($reused->validate($adminWithoutNickname)->anyFailed());
			$this->assertFalse($reused->validate($bobWithNickname)->anyFailed());
		}
	}

	#[Test]
	public function prefill_still_leaks_between_concurrent_requests(): void
	{
		// B9, and the last instance of B7's shape. prefill() writes to the schema exactly
		// as input() used to, so a worker that fills in what it knows about a user — their
		// saved email, their last address — puts one request's data where another request
		// reads it.
		//
		// This asserts the *defect*, so it fails the moment prefilling moves to resolution.
		// When that happens, replace the body with the isolation assertion below it.
		$schema = new Facade('profile');
		$schema->addTextField('email');

		$request = static fn(string $email): Fiber => new Fiber(
			static function () use ($schema, $email): mixed {
				$schema->prefill(['email' => $email]);
				Fiber::suspend();

				return $schema->validate([])->get('email')->value;
			},
		);

		$alice = $request('alice@example.com');
		$mallory = $request('mallory@example.com');

		$alice->start();
		$mallory->start();
		$alice->resume();
		$mallory->resume();

		// What it should be: 'alice@example.com'.
		$this->assertSame('mallory@example.com', $alice->getReturn(), 'B9 appears to be fixed — invert this test.');

		// And the value outlives the request that supplied it.
		$this->assertStringContainsString('mallory@example.com', serialize($schema));
	}
}
