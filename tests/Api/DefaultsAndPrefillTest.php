<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use Meraki\Schema\Facade;
use Meraki\Schema\PrefillPolicy;
use Meraki\Schema\ValidationStatus;
use Meraki\Schema\ValueSource;
use InvalidArgumentException;
use Fiber;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

/**
 * The split that closes B9: an authored constant lives on the definition and serialises; a
 * value fetched for one user arrives with the request and never touches the schema.
 */
#[CoversNothing]
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
		$resolved = $this->schema()->resolve((object)['username' => 'alice'])->forField('nickname');

		$this->assertSame('anonymous', $resolved->value->text);
		$this->assertSame(ValueSource::Default, $resolved->source);
	}

	#[Test]
	public function a_prefill_beats_the_authored_default(): void
	{
		$resolved = $this->schema()
			->resolve((object)['username' => 'alice'], prefilledWith: (object)['nickname' => 'ali'])
			->forField('nickname');

		$this->assertSame('ali', $resolved->value->text);
		$this->assertSame(ValueSource::Prefilled, $resolved->source);
	}

	#[Test]
	public function what_was_submitted_beats_a_prefill(): void
	{
		$resolved = $this->schema()
			->resolve((object)['username' => 'alice', 'nickname' => 'typed'], prefilledWith: (object)['nickname' => 'ali'])
			->forField('nickname');

		$this->assertSame('typed', $resolved->value->text);
		$this->assertSame(ValueSource::Submitted, $resolved->source);
	}

	#[Test]
	public function a_prefill_is_checked_by_default(): void
	{
		// The scenario that decides it: a constraint tightens and stored values no longer
		// satisfy it. Checked surfaces that so the user fixes it.
		$result = $this->schema()->validate(null, prefilledWith: (object)['username' => 'ab']);

		$this->assertTrue($result->forField('username')->anyFailed());
	}

	#[Test]
	public function a_trusted_prefill_skips_the_constraints_but_still_passes(): void
	{
		$resolved = $this->schema()
			->validate(null, prefilledWith: (object)['username' => 'ab'], policy: PrefillPolicy::Trusted)
			->forField('username');

		$this->assertFalse($resolved->anyFailed());
		$this->assertSame('ab', $resolved->value->text);

		// Passed, not Skipped. Skipped means there was nothing to check at all; this field
		// plainly has a value, and trust waives the rules rather than the value's existence.
		// The shape still had to pass to get here — trust says a value meets the rules, not
		// that the field can read it.
		$this->assertTrue($resolved->shape->passed());
		$this->assertSame(ValidationStatus::Passed, $resolved->status);
	}

	#[Test]
	public function a_trusted_prefill_does_not_excuse_what_was_submitted(): void
	{
		// Trust attaches to a value, and a prefilled value only survives when nothing
		// overwrote it.
		$result = $this->schema()
			->validate((object)['username' => 'ab'], prefilledWith: (object)['username' => 'alice'], policy: PrefillPolicy::Trusted);

		$this->assertTrue($result->forField('username')->anyFailed());
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
				$resolved = $schema->resolve(null, prefilledWith: (object)['email' => $email]);

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
		$schema = new Facade('profile');
		$schema->add($schema->createTextField('email'));

		$schema->validate(null, prefilledWith: (object)['email' => 'alice-pii@example.com']);

		$this->assertStringNotContainsString('alice-pii', self::dump($schema));
	}

	#[Test]
	public function a_serialised_schema_can_never_contain_user_data(): void
	{
		// The guarantee the split buys: the definition holds only constants the author
		// typed, so there is nowhere for a request to end up.
		$schema = $this->schema();

		$schema->validate((object)['username' => 'alice'], prefilledWith: (object)['nickname' => 'ali']);

		$dumped = self::dump($schema);

		$this->assertStringNotContainsString('alice', $dumped);
		$this->assertStringNotContainsString('ali', $dumped);

		// The authored default is still there, which is the other half of the claim: a
		// definition keeps what its author typed and nothing a request brought with it.
		$this->assertStringContainsString('anonymous', $dumped);
	}

	/**
	 * The whole object graph as text, for asserting that something is *not* in it.
	 *
	 * `serialize()` would be the obvious tool and cannot be used: a constraint holds a `Closure`,
	 * so a schema is not PHP-serialisable at all. That is not a gap — serialising a schema is
	 * `meraki/schema-json`'s job, and it writes the *definition* rather than the object graph,
	 * which is exactly the distinction this test is about.
	 *
	 * `print_r()` walks the graph the same way and prints a closure as a closure, so a value
	 * hidden anywhere in a field, a constraint's bound or a rule would still show up here.
	 */
	private static function dump(Facade $schema): string
	{
		return print_r($schema, true);
	}
}
