<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Secret;

use Meraki\Schema\Field\Secret\PassphrasePolicy;
use Meraki\Schema\Field\Secret\PolicyTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use InvalidArgumentException;

#[Group('field')]
#[CoversClass(PassphrasePolicy::class)]
final class PassphrasePolicyTest extends PolicyTestCase
{
	public function createPolicy(): PassphrasePolicy
	{
		return new PassphrasePolicy();
	}

	#[Test]
	public function it_throws_if_entropy_is_less_than_1(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new PassphrasePolicy(0);
	}

	#[Test]
	public function it_serializes_to_string(): void
	{
		$policy = new PassphrasePolicy(80, 'standard', 'none');

		$policyAsString = (string)$policy;

		$this->assertEquals($policy, PassphrasePolicy::parse($policyAsString));
	}

	#[Test]
	public function it_parses_from_valid_string(): void
	{
		$policy = PassphrasePolicy::parse('entropy:90;method:standard;dictionary:none');

		$this->assertSame(90, $policy->entropy);
		$this->assertSame('standard', $policy->method);
		$this->assertSame('none', $policy->dictionary);
	}

	#[Test]
	public function it_throws_when_parsing_missing_entropy(): void
	{
		$this->expectException(InvalidArgumentException::class);

		PassphrasePolicy::parse('method:standard');
	}

	#[Test]
	public function it_throws_when_parsing_unknown_key(): void
	{
		$this->expectException(InvalidArgumentException::class);

		PassphrasePolicy::parse('entropy:72;foo:bar');
	}

	#[Test]
	public function it_throws_when_method_is_invalid(): void
	{
		$policy = new PassphrasePolicy(72, 'nonsense', 'none');

		$this->expectException(InvalidArgumentException::class);

		$policy->matches('correct horse battery staple');
	}

	#[Test]
	public function it_throws_when_dictionary_is_invalid(): void
	{
		$policy = new PassphrasePolicy(72, 'standard', 'common-words');

		$this->expectException(InvalidArgumentException::class);

		$policy->matches('correct horse battery staple');
	}

	#[Test]
	#[DataProvider('passphraseSamples')]
	public function it_calculates_entropy_and_matches_expected_result(
		string $passphrase,
		int $requiredEntropy,
		bool $expectedResult
	): void {
		$policy = new PassphrasePolicy($requiredEntropy);

		$this->assertEquals($expectedResult, $policy->matches($passphrase), "Failed for: {$passphrase}");
	}

	/**
	 * Examples show short passphrases with diverse characters can pass
	 * while longer but less diverse may not.
	 */
	public static function passphraseSamples(): array
	{
		return [
			// High diversity, short length
			['A$9ü', 40, true],
			['A$9ü', 60, false],

			// Low diversity, long length
			['aaaaaaaaaaaaaaaaaaaa', 94, true],
			['aaaaaaaaaaaaaaaaaaaa', 128, false],

			// Mixed but medium length
			['abc123ABC!@#', 79, true],
			['abc123ABC!@#', 100, false],

			// Pure ASCII, decent length
			['Th1sIsAStrongP@ssword', 138, true],
			['Th1sIsAStrongP@ssword', 160, false],

			// Unicode-rich short passphrase
			['ΨΔЖא9', 50, true],
			['ΨΔЖא9', 80, false],
		];
	}
}
