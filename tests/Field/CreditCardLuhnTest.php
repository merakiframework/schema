<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Facade;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The field checked the shape of a card number but never its checksum, so it accepted
 * numbers no payment processor would.
 */
#[Group('field')]
#[CoversClass(CreditCard::class)]
final class CreditCardLuhnTest extends TestCase
{
	/** @return array<string, array{string}> */
	public static function validNumbers(): array
	{
		return [
			'visa'             => ['4111111111111111'],
			'visa 13-digit'    => ['4222222222222'],
			'mastercard'       => ['5555555555554444'],
			'amex 15-digit'    => ['378282246310005'],
			'discover'         => ['6011111111111117'],
			'jcb'              => ['3530111333300000'],
			'19-digit'         => ['6011111111111117000'],
		];
	}

	/** @return array<string, array{string}> */
	public static function invalidNumbers(): array
	{
		return [
			'last digit wrong'   => ['4111111111111112'],
			'transposed digits'  => ['4111111111111121'],
			'all zeroes but one' => ['4000000000000001'],
			'mastercard broken'  => ['5555555555554443'],
		];
	}

	private function validate(string $number): bool
	{
		$schema = new Facade('payment');
		$schema->addCreditCardField('card');

		return $schema->validate(['card' => [
			'holder' => 'Jane Doe',
			'number' => $number,
			'expiry' => '2030-01',
			'security_code' => '123',
		]])->anyFailed();
	}

	#[Test]
	#[DataProvider('validNumbers')]
	public function a_number_with_a_valid_checksum_passes(string $number): void
	{
		$this->assertFalse($this->validate($number));
	}

	#[Test]
	#[DataProvider('invalidNumbers')]
	public function a_number_with_a_bad_checksum_fails(string $number): void
	{
		$this->assertTrue($this->validate($number));
	}

	#[Test]
	public function the_shape_checks_still_apply(): void
	{
		// Too short, and non-numeric, are still caught before the checksum is meaningful.
		$this->assertTrue($this->validate('411111'));
		$this->assertTrue($this->validate('4111abcd11111111'));
	}

	#[Test]
	public function whitespace_in_the_number_is_still_tolerated(): void
	{
		// process() strips it, the way a card is printed and typed.
		$this->assertFalse($this->validate('4111 1111 1111 1111'));
	}

	#[Test]
	public function the_failure_is_reported_against_the_number(): void
	{
		$schema = new Facade('payment');
		$schema->addCreditCardField('card');

		$result = $schema->validate(['card' => [
			'holder' => 'Jane Doe',
			'number' => '4111111111111112',
			'expiry' => '2030-01',
			'security_code' => '123',
		]]);

		$failed = [];

		foreach ($result as $composite) {
			foreach ($composite as $fieldResult) {
				foreach ($fieldResult as $constraint) {
					if ($constraint->status->name === 'Failed') {
						$failed[(string) $fieldResult->field->name][] = $constraint->name;
					}
				}
			}
		}

		$this->assertArrayHasKey('card.number', $failed);
		$this->assertContains('card.number.checksum', $failed['card.number']);
	}
}
