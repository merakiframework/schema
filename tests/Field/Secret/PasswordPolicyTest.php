<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Secret;

use Meraki\Schema\Field\Secret\PasswordPolicy;
use Meraki\Schema\Field\Secret\PolicyTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use InvalidArgumentException;

#[Group('field')]
#[CoversClass(PasswordPolicy::class)]
final class PasswordPolicyTest extends PolicyTestCase
{
	public function createPolicy(): PasswordPolicy
	{
		return new PasswordPolicy();
	}

	#[Test]
	public function it_has_the_correct_name(): void
	{
		$policy = $this->createPolicy();

		$this->assertEquals('password_policy', $policy->name);
	}

	#[Test]
	public function it_accepts_a_valid_anyof_group(): void
	{
		$policy = new PasswordPolicy(
			digits: [1, null],
			symbols: [1, null],
			anyof: ['digits', 'symbols']
		);

		$this->assertTrue($policy->matches('hello$'));
		$this->assertTrue($policy->matches('hello7'));
		$this->assertTrue($policy->matches('hel1o$'));
		$this->assertFalse($policy->matches('hello'));
	}

	#[Test]
	public function it_rejects_anyof_group_with_invalid_key(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new PasswordPolicy(anyof: ['letters', 'symbols']);
	}

	#[Test]
	public function it_rejects_anyof_group_with_only_one_item(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new PasswordPolicy(anyof: ['digits']);
	}

	#[Test]
	public function it_can_parse_simple_policy(): void
	{
		$policy = PasswordPolicy::parse('length:8,20;digits:1,;symbols:1,;');

		$this->assertEquals([8,20], $policy->length);
		$this->assertEquals([1, $policy::UNRESTRICTED], $policy->digits);
		$this->assertEquals([1, $policy::UNRESTRICTED], $policy->symbols);
		$this->assertEquals([], $policy->uppercase);
		$this->assertEquals([], $policy->lowercase);
		$this->assertEquals([], $policy->anyof);
	}

	#[Test]
	public function it_can_parse_anyof_policy(): void
	{
		$policy = PasswordPolicy::parse('length:4,10;anyof:digits,symbols');

		$this->assertEquals([4, 10], $policy->length);
		$this->assertEquals([], $policy->uppercase);
		$this->assertEquals([], $policy->lowercase);
		$this->assertEquals([], $policy->digits);
		$this->assertEquals([], $policy->symbols);
		$this->assertEquals(['digits', 'symbols'], $policy->anyof);
	}

	#[Test]
	public function it_converts_to_string_correctly(): void
	{
		$original = new PasswordPolicy(
			length: [8, 32],
			uppercase: [1, null],
			lowercase: [1, null],
			digits: [1, null],
			symbols: [1, null],
			anyof: ['digits', 'symbols']
		);

		$string = (string) $original;
		$parsed = PasswordPolicy::parse($string);

		$this->assertEquals($original, $parsed);
	}

	#[Test]
	public function strong_password_policy_is_valid(): void
	{
		$policy = PasswordPolicy::strong();

		$this->assertTrue($policy->matches('Str0ng@Passw0rd!'));
		$this->assertFalse($policy->matches('weakpass'));
	}

	#[Test]
	public function moderate_password_policy_is_valid(): void
	{
		$policy = PasswordPolicy::moderate();

		$this->assertTrue($policy->matches('Mod3rate!Pass'));
		$this->assertFalse($policy->matches('noDigitsOrUpper'));
	}

	#[Test]
	public function weak_password_policy_is_valid(): void
	{
		$policy = PasswordPolicy::weak();

		$this->assertTrue($policy->matches('anystring'));
		$this->assertFalse($policy->matches('short'));
	}

	#[Test]
	public function it_throws_on_invalid_tuple_format(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new PasswordPolicy(length: [1, 2, 3]);
	}

	#[Test]
	public function it_throws_on_negative_min(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new PasswordPolicy(length: [-1, 10]);
	}

	#[Test]
	public function it_throws_when_min_greater_than_max(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new PasswordPolicy(length: [10, 5]);
	}
}
