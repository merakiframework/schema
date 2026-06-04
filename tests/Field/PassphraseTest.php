<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Passphrase;
use Meraki\Schema\Property\Name;
use Meraki\Schema\FieldTestCase;
use Meraki\Schema\ValidationStatus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use InvalidArgumentException;

#[Group('field')]
#[CoversClass(Passphrase::class)]
final class PassphraseTest extends FieldTestCase
{
	public function createField(): Passphrase
	{
		return new Passphrase(new Name('passphrase'), entropy: 72);
	}

	#[Test]
	public function it_throws_if_entropy_is_less_than_1(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$passphrase = new Passphrase(new Name('passphrase'), entropy: 0);
	}

	#[Test]
	public function it_throws_when_method_is_invalid(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$policy = new Passphrase(new Name('passphrase'), method: 'nonsense');
	}

	#[Test]
	public function it_throws_when_dictionary_is_invalid(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$policy = new Passphrase(new Name('passphrase'), dictionary: 'common-words');
	}

	#[Test]
	#[DataProvider('passphraseSamples')]
	public function it_calculates_entropy_and_matches_expected_result(
		string $passphrase,
		int $requiredEntropy,
		ValidationStatus $expectedStatus
	): void {
		$policy = new Passphrase(new Name('passphrase'), entropy: $requiredEntropy, method: 'standard', dictionary: 'none');
		$policy->input($passphrase);

		$result = $policy->validate();

		$this->assertConstraintValidationResultHasStatusOf($expectedStatus, 'entropy', $result);
	}

	/**
	 * Examples show short passphrases with diverse characters can pass
	 * while longer but less diverse may not.
	 */
	public static function passphraseSamples(): array
	{
		return [
			// High diversity, short length
			['A$9ü', 40, ValidationStatus::Passed],
			['A$9ü', 60, ValidationStatus::Failed],

			// Low diversity, long length
			['aaaaaaaaaaaaaaaaaaaa', 94, ValidationStatus::Passed],
			['aaaaaaaaaaaaaaaaaaaa', 128, ValidationStatus::Failed],

			// Mixed but medium length
			['abc123ABC!@#', 79, ValidationStatus::Passed],
			['abc123ABC!@#', 100, ValidationStatus::Failed],

			// Pure ASCII, decent length
			['Th1sIsAStrongP@ssword', 138, ValidationStatus::Passed],
			['Th1sIsAStrongP@ssword', 160, ValidationStatus::Failed],

			// Unicode-rich short passphrase
			['ΨΔЖא9', 50, ValidationStatus::Passed],
			['ΨΔЖא9', 80, ValidationStatus::Failed],

			// Full Unicode passphrase
			['correct horse battery staple', 132, ValidationStatus::Passed],
			['correct horse battery staple', 180, ValidationStatus::Failed],
		];
	}

	#[Test]
	public function it_has_no_value_by_default(): void
	{
		$field = $this->createField();

		$this->assertNull($field->value->unwrap());
	}

	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$field = $this->createField();

		$this->assertNull($field->defaultValue->unwrap());
	}
}
