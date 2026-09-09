<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use Meraki\Schema\Field;
use Meraki\Schema\Field\Password\Strength;
use Meraki\Schema\Property;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A field encodes current best practice, and configuration narrows from there. It never
 * widens, so a field with no configuration is already correct and cannot be talked down.
 */
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
		$name = new Property\Name('f');

		yield 'a password shorter than the recommended floor' => [
			static fn() => (new Field\Password($name))->minLengthOf(4),
			'8 is the floor in current guidance.',
		];

		yield 'an email shorter than the shortest possible address' => [
			static fn() => (new Field\EmailAddress($name))->minLengthOf(2),
			'"a@b" is three characters, so nothing shorter can be an address.',
		];

		yield 'a password longer than the hash accepts' => [
			static fn() => (new Field\Password($name))->maxLengthOf(200),
			'bcrypt truncates at 72 bytes.',
		];
	}

	#[Test]
	public function a_baseline_is_readable_but_has_no_setter(): void
	{
		// Consumers need to show the number; nobody may change it without a core release.
		// The missing setter *is* that guarantee, rather than a comment saying so.
		$password = new Field\Password(new Property\Name('secret'));

		$this->assertSame(72, $password->maxBytes);
		$this->assertFalse(method_exists($password, 'maxBytesOf'));
	}

	#[Test]
	public function a_baseline_does_not_serialise(): void
	{
		// It follows from the type, so persisting it lets a stored document disagree with
		// the code once the core moves.
		$password = new Field\Password(new Property\Name('secret'));

		$this->assertStringNotContainsString('maxBytes', serialize($password));
	}

	#[Test]
	public function length_is_counted_in_characters(): void
	{
		$password = new Field\Password(new Property\Name('secret'));
		$password->maxLengthOf(64);

		// 64 CJK characters is 64 characters and 192 bytes.
		$this->assertFalse($password->validate(str_repeat('密', 64))->get('maxLength')->failed());
	}

	#[Test]
	public function the_ceiling_is_counted_in_bytes(): void
	{
		// The case a character limit cannot express, and the one bcrypt would silently
		// truncate: on PHP 8.5 a hash of 72 "a"s verifies a string of 80.
		$password = new Field\Password(new Property\Name('secret'));

		$result = $password->validate(str_repeat('密', 64));

		$this->assertTrue($result->get('maxBytes')->failed());
		$this->assertSame(72, $result->get('maxBytes')->bound);
	}

	#[Test]
	public function strength_is_a_floor_expressed_as_a_tier(): void
	{
		$password = new Field\Password(new Property\Name('secret'));
		$password->minStrengthOf(Strength::Strong);

		$this->assertTrue($password->validate('password1')->get('minStrength')->failed());
		$this->assertFalse($password->validate('correct horse battery staple xyzzy')->get('minStrength')->failed());
	}

	#[Test]
	public function strength_is_a_constraint_rather_than_the_shape(): void
	{
		// A weak password is a well-formed string that failed a judgement, not a malformed
		// one — so the message can say "too predictable" rather than "not a password".
		$password = new Field\Password(new Property\Name('secret'));
		$password->minStrengthOf(Strength::Strong);

		$resolved = $password->validate('password1');

		$this->assertSame('password1', $resolved->value);
		$this->assertTrue($resolved->get('minStrength')->failed());
	}
}
