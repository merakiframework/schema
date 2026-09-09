<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use Meraki\Schema\Facade;
use Meraki\Schema\PrefillPolicy;
use Meraki\Schema\ValueSource;
use InvalidArgumentException;
use Fiber;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

/**
 * The split that closes B9: an authored constant lives on the definition and serialises; a
 * value fetched for one user arrives with the request and never touches the schema.
 */
#[Group('api-2.0')]
final class DefaultsAndPrefillTest extends TestCase
{
	private function schema(): Facade
	{
		$schema = new Facade('signup');
		$schema->add($schema->createTextField('username')->minLengthOf(3));
		$schema->add($schema->createTextField('nickname')->defaultsTo('anonymous'));

		return $schema;
	}

	#[Test]
	public function an_authored_default_applies_when_nothing_is_submitted(): void
	{
		$resolved = $this->schema()->resolve(['username' => 'alice'])->get('nickname');

		$this->assertSame('anonymous', $resolved->value);
		$this->assertSame(ValueSource::Default, $resolved->source);
	}

	#[Test]
	public function a_prefill_beats_the_authored_default(): void
	{
		$resolved = $this->schema()
			->resolve(['username' => 'alice'], prefilledWith: ['nickname' => 'ali'])
			->get('nickname');

		$this->assertSame('ali', $resolved->value);
		$this->assertSame(ValueSource::Prefilled, $resolved->source);
	}

	#[Test]
	public function what_was_submitted_beats_a_prefill(): void
	{
		$resolved = $this->schema()
			->resolve(['username' => 'alice', 'nickname' => 'typed'], prefilledWith: ['nickname' => 'ali'])
			->get('nickname');

		$this->assertSame('typed', $resolved->value);
		$this->assertSame(ValueSource::Submitted, $resolved->source);
	}

	#[Test]
	public function a_prefill_is_checked_by_default(): void
	{
		// The scenario that decides it: a constraint tightens and stored values no longer
		// satisfy it. Checked surfaces that so the user fixes it.
		$result = $this->schema()->validate([], prefilledWith: ['username' => 'ab']);

		$this->assertTrue($result->get('username')->anyFailed());
	}

	#[Test]
	public function a_trusted_prefill_skips_the_constraints_but_still_passes(): void
	{
		$resolved = $this->schema()
			->validate([], prefilledWith: ['username' => 'ab'], policy: PrefillPolicy::Trusted)
			->get('username');

		$this->assertFalse($resolved->anyFailed());
		$this->assertSame('ab', $resolved->value);

		// Passed, not Skipped: Skipped means there was nothing to check, and would make
		// transformed return null for a field that plainly has a value.
		$this->assertSame('ab', $resolved->transformed);
	}

	#[Test]
	public function a_trusted_prefill_does_not_excuse_what_was_submitted(): void
	{
		// Trust attaches to a value, and a prefilled value only survives when nothing
		// overwrote it.
		$result = $this->schema()
			->validate(['username' => 'ab'], prefilledWith: ['username' => 'alice'], policy: PrefillPolicy::Trusted);

		$this->assertTrue($result->get('username')->anyFailed());
	}

	#[Test]
	public function an_authored_default_is_checked_when_it_is_declared(): void
	{
		// An invalid default is a bug in the schema, and blaming a user's request for it
		// would be the wrong place to find out.
		$schema = new Facade('signup');

		$this->expectException(InvalidArgumentException::class);

		$schema->createTextField('username')->defaultsTo('ab')->minLengthOf(5);
	}

	#[Test]
	public function prefilling_does_not_leak_between_concurrent_requests(): void
	{
		// B9. The same shape as B7, in the method that survived it.
		$schema = new Facade('profile');
		$schema->add($schema->createTextField('email'));

		$request = static fn(string $email): Fiber => new Fiber(
			static function () use ($schema, $email): mixed {
				$resolved = $schema->resolve([], prefilledWith: ['email' => $email]);

				Fiber::suspend();

				return $resolved->get('email')->value;
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
		$schema = new Facade('profile');
		$schema->add($schema->createTextField('email'));

		$schema->validate([], prefilledWith: ['email' => 'alice-pii@example.com']);

		$this->assertStringNotContainsString('alice-pii', serialize($schema));
	}

	#[Test]
	public function a_serialised_schema_can_never_contain_user_data(): void
	{
		// The guarantee the split buys: the definition holds only constants the author
		// typed, so there is nowhere for a request to end up.
		$schema = $this->schema();

		$schema->validate(['username' => 'alice'], prefilledWith: ['nickname' => 'ali']);

		$serialised = serialize($schema);

		$this->assertStringNotContainsString('alice', $serialised);
		$this->assertStringNotContainsString('ali', $serialised);
		$this->assertStringContainsString('anonymous', $serialised);
	}
}
