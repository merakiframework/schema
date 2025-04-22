<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Type;


use Meraki\Schema\Field\Type\EmailAddress;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass, DataProvider};

#[CoversClass(EmailAddress::class)]
final class EmailAddressTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$emailAddress = $this->createField();

		$this->assertInstanceOf(EmailAddress::class, $emailAddress);
	}

	public function createField(): EmailAddress
	{
		return new EmailAddress(new Attribute\Name('email_address'));
	}

	#[Test]
	#[DataProvider('validEmailAddresses')]
	public function it_validates_valid_emails(string $emailAddress): void
	{
		$field = $this->createField()
			->input($emailAddress);

		$this->assertTrue($field->validationResult->passed());
	}

	#[Test]
	#[DataProvider('invalidEmailAddresses')]
	public function it_does_not_validate_invalid_emails(string $emailAddress): void
	{
		$field = $this->createField()
			->input($emailAddress);

		$this->assertTrue($field->validationResult->failed());
	}

	#[test]
	public function it_can_validate_multiple_email_addresses(): void
	{
		$field = $this->createField()
			->allowMultiple()
			->input('user1@domain,user2@domain.com');

		$this->assertTrue($field->validationResult->passed());
	}

	#[test]
	public function it_can_validate_only_one_email_address_when_multiples_are_allowed(): void
	{
		$field = $this->createField()
			->allowMultiple()
			->input('user1@domain');

		$this->assertTrue($field->validationResult->passed());
	}

	#[test]
	public function it_fails_validation_if_one_of_multiple_email_addresses_is_not_valid(): void
	{
		$field = $this->createField()
			->allowMultiple()
			->input('user1@domain,user2,user3@domain.com');

		$this->assertTrue($field->validationResult->failed());
	}

	public static function validEmailAddresses(): array
	{
		return [
			'simple' => ['user@domain'],
		];
	}

	public static function invalidEmailAddresses(): array
	{
		return [
			'nothing provided' => [''],
			'missing @' => ['userdomain'],
			'missing user' => ['@domain'],
			'missing domain' => ['user@'],
		];
	}

	public function usesConstraints(): bool
	{
		return true;
	}

	public function getExpectedType(): string
	{
		return 'email_address';
	}

	public function getValidValue(): string
	{
		return 'user@domain';
	}

	public function getInvalidValue(): string
	{
		return '';
	}

	public function createValidConstraint(): Constraint
	{
		return new Attribute\Max(30);
	}

	public function createInvalidConstraint(): Constraint
	{
		return new Attribute\Max(1);
	}
}
