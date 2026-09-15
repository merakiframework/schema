<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use Meraki\Schema\Field;
use Meraki\Schema\Field\Password\Strength;
use Meraki\Schema\FieldName;
use InvalidArgumentException;
use ReflectionClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A field encodes current best practice, and configuration narrows from there. It never
 * widens, so a field with no configuration is already correct and cannot be talked down.
 */
#[CoversNothing]
#[Group('api-2.0')]
final class BaselineTest extends TestCase
{
	#[Test]
	#[DataProvider('attemptsToWidenTheBaseline')]
	public function configuration_cannot_go_below_the_baseline(callable $attempt, string $why): void
	{
		$this->expectException(InvalidArgumentException::class);

		$attempt();
	}

	/** @return iterable<string, array{callable, string}> */
	public static function attemptsToWidenTheBaseline(): iterable
	{
		$name = new FieldName('f');

		yield 'a password shorter than the recommended floor' => [
			static fn() => (new Field\Password($name))->minLengthOf(4),
			'8 is the floor in current guidance.',
		];

		yield 'an email shorter than the shortest possible address' => [
			static fn() => (new Field\EmailAddress($name))->minLengthOf(2),
			'"a@b" is three characters, so nothing shorter can be an address.',
		];

	}

	/**
	 * A baseline is a constant, which is a stronger guarantee than a property without a setter.
	 *
	 * Consumers need to be able to show the number, and nobody may change it without a core
	 * release. As a constant it is not object state at all, so there is nothing to serialise into
	 * a stored document that could later disagree with the code — which is what the earlier
	 * "does not serialise" test was reaching for.
	 *
	 * `maxBytes` used to be the example here and is gone. What a hashing algorithm can swallow is
	 * the hashing layer's question; expressing it as validation put it in the wrong layer and asked
	 * the author to tell the field something it could not otherwise know. See Field\Password.
	 */
	#[Test]
	public function a_baseline_is_a_constant_rather_than_configuration(): void
	{
		$this->assertSame(8, Field\Password::SHORTEST);

		$reflected = new ReflectionClass(Field\Password::class);

		$this->assertFalse($reflected->hasProperty('shortest'), 'A baseline must not be object state.');
		$this->assertFalse($reflected->hasMethod('shortestOf'), 'A baseline must have no setter.');
	}

	#[Test]
	public function length_is_counted_in_characters(): void
	{
		// Reassigned, not called and discarded: a field is sealed, so `maxLengthOf()` hands back a
		// copy and the original is untouched. Written the other way this test configured nothing
		// and passed for the wrong reason.
		$password = (new Field\Password(new FieldName('secret')))->maxLengthOf(64);

		// 64 CJK characters is 64 characters and 192 bytes.
		$this->assertFalse($password->validate(str_repeat('密', 64))->forConstraint('maxLength')->failed());
	}


	#[Test]
	public function strength_is_a_floor_expressed_as_a_tier(): void
	{
		$password = (new Field\Password(new FieldName('secret')))->minStrengthOf(Strength::Strong);

		$this->assertTrue($password->validate('password1')->forConstraint('minStrength')->failed());
		$this->assertFalse($password->validate('correct horse battery staple xyzzy')->forConstraint('minStrength')->failed());
	}

	#[Test]
	public function strength_is_a_constraint_rather_than_the_shape(): void
	{
		// A weak password is a well-formed string that failed a judgement, not a malformed
		// one — so the message can say "too predictable" rather than "not a password".
		$password = (new Field\Password(new FieldName('secret')))->minStrengthOf(Strength::Strong);

		$resolved = $password->validate('password1');

		$this->assertSame('password1', $resolved->value->secret);
		$this->assertTrue($resolved->forConstraint('minStrength')->failed());
	}
}
