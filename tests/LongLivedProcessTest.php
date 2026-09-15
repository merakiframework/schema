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
		$schema->add($schema->createTextField('username')->minLengthOf(3));
		$schema->add($schema->createTextField('nickname')->makeOptional());
		$schema->addRule($schema->when('username')->equals('admin')->thenRequire('nickname'));

		return $schema;
	}

	#[Test]
	public function two_requests_interleaved_mid_validation_do_not_see_each_others_data(): void
	{
		$schema = $this->bootSchema();

		$request = static fn(string $username): Fiber => new Fiber(
			static function () use ($schema, $username): mixed {
				$result = $schema->validate((object)['username' => $username]);

				// Whatever the handler does next — a query, an HTTP call — is where a
				// coroutine yields and its neighbour runs.
				Fiber::suspend();

				return $result->forField('username')->value->text;
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
		$before = print_r($schema, true);

		$clone = clone $schema;
		$clone->validate((object)['username' => 'admin', 'nickname' => 'root']);

		$this->assertSame($before, print_r($schema, true));
	}

	#[Test]
	public function submitted_data_is_not_retained_after_the_request(): void
	{
		// A worker lives for days. Anything a field keeps hold of is user data sitting in
		// memory long after the request that supplied it has gone.
		$schema = $this->bootSchema();

		$schema->validate((object)[
			'username' => 'alice-must-not-persist',
			'nickname' => 'nickname-must-not-persist',
		]);

		$retained = print_r($schema, true);

		$this->assertStringNotContainsString('alice-must-not-persist', $retained);
		$this->assertStringNotContainsString('nickname-must-not-persist', $retained);
	}

	#[Test]
	public function validating_does_not_change_the_schema(): void
	{
		// The broadest of the five: whatever else validation does, the definition it ran
		// against must come out identical, whether a rule matched or not.
		$schema = $this->bootSchema();
		$before = print_r($schema, true);

		$schema->validate((object)['username' => 'admin']);                       // rule matches
		$schema->validate((object)['username' => 'bob', 'nickname' => 'bobby']);   // rule does not
		$schema->resolve((object)['username' => 'carol']);

		$this->assertSame($before, print_r($schema, true));
	}

	#[Test]
	public function repeated_validation_is_order_independent(): void
	{
		// RoadRunner's model: one request at a time, but thousands of them against the
		// same instance. An answer must not depend on what the worker saw before it.
		$adminWithoutNickname = (object)['username' => 'admin'];                 // fails
		$bobWithNickname = (object)['username' => 'bob', 'nickname' => 'bobby']; // passes

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
		// B9, and the last instance of B7's shape. `prefill()` wrote the values onto the fields
		// exactly as `input()` used to, so a worker filling in what it knew about a user — their
		// saved email, their last address — put one request's data where the next request read
		// it. This test used to assert the *defect*, with a note to invert it once prefilling
		// moved to resolution. It has.
		$schema = new Facade('profile');
		$schema->add($schema->createTextField('email'));

		$request = static fn(string $email): Fiber => new Fiber(
			static function () use ($schema, $email): mixed {
				$resolved = $schema->validate(prefilledWith: (object)['email' => $email]);

				// Where a coroutine yields and its neighbour runs.
				Fiber::suspend();

				return $resolved->forField('email')->value->text;
			},
		);

		$alice = $request('alice@example.com');
		$mallory = $request('mallory@example.com');

		$alice->start();
		$mallory->start();
		$alice->resume();
		$mallory->resume();

		$this->assertSame('alice@example.com', $alice->getReturn());
		$this->assertSame('mallory@example.com', $mallory->getReturn());
	}

	#[Test]
	public function a_prefilled_value_is_never_retained_by_the_schema(): void
	{
		// The other half of B9: not merely that two requests cannot see each other's data, but
		// that the schema keeps none of it once the request is over. A long-lived worker holds
		// this object for the life of the process.
		$schema = new Facade('profile');
		$schema->add($schema->createTextField('email'));

		$schema->validate(prefilledWith: (object)['email' => 'alice-pii@example.com']);

		$this->assertStringNotContainsString('alice-pii', print_r($schema, true));
	}
}
