<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\EmailAddress;
use Meraki\Schema\ValidationStatus;
use Meraki\Schema\Field\EmailAddress\Format;
use Meraki\Schema\Property\Name;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[Group('field')]
#[CoversClass(EmailAddress::class)]
final class EmailAddressTest extends FieldTestCase
{
	public function createField(): EmailAddress
	{
		return new EmailAddress(new Name('email_address'));
	}

	#[Test]
	#[DataProvider('emailAddressesForBasicFormat')]
	public function it_meet_expectations_for_basic_format(mixed $emailAddress, ValidationStatus $expectedStatus): void
	{
		$field = new EmailAddress(new Name('email_address'), format: Format::Basic);
		$field->input($emailAddress);

		$result = $field->validate();

		$this->assertConstraintValidationResultHasStatusOf($expectedStatus, 'type', $result);
	}

	public static function emailAddressesForBasicFormat(): array
	{
		return [
			'no tld' => ['user@domain', ValidationStatus::Passed],
			'multiple subdomains' => ['user@example.com', ValidationStatus::Passed],
			'ipv4 address for domain' => ['user@192.168.0.1', ValidationStatus::Passed],
			'ipv6 address for domain' => ['user@[::1]', ValidationStatus::Passed],
			'quoted string' => ['"user"@domain', ValidationStatus::Passed],
			'nothing provided' => ['', ValidationStatus::Failed],
			'missing @' => ['userdomain', ValidationStatus::Failed],
			'missing user' => ['@domain', ValidationStatus::Failed],
			'missing domain' => ['user@', ValidationStatus::Failed],
			'too many @' => ['user@domain@domain', ValidationStatus::Failed],
			'quoted string with secondary @' => ['"user@domain"@domain', ValidationStatus::Failed],
			'valid email address list' => ['user@domain, "Michael O\'Flannigan"@example.net, "Doe, John"@example.net', ValidationStatus::Passed],
			'email address list with missing email address' => ['user@domain, , "michael o\'flannigan"@example.net, "a, b"@example.net', ValidationStatus::Failed],
			'email address list with invalid email address' => ['user@domain, @example.net, "a, b"@example.net', ValidationStatus::Failed],
		];
	}

	#[Test]
	#[DataProvider('supportedEmailFormats')]
	public function a_default_minimum_length_is_set(Format $emailFormat): void
	{
		$field = $this->createField();

		$this->assertSame($emailFormat->getAllowableMinLengthTotal(), $field->min);
	}

	#[Test]
	#[DataProvider('supportedEmailFormats')]
	public function a_default_maximum_length_is_set(Format $emailFormat): void
	{
		$field = $this->createField();

		$this->assertSame($emailFormat->getAllowableMaxLengthTotal(), $field->max);
	}

	public static function supportedEmailFormats(): array
	{
		return [
			'Basic' => [Format::Basic],
			'HTML' => [Format::Html],
			'RFC' => [Format::Rfc],
			'SMTP' => [Format::Smtp],
		];
	}

	#[Test]
	public function it_validates_min_length_when_met(): void
	{
		$field = $this->createField()
			->minLengthOf(5)
			->input('user@domain');

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('min', $result);
	}

	#[Test]
	public function it_does_not_validate_min_length_when_not_met(): void
	{
		$field = $this->createField()
			->minLengthOf(5)
			->input('a@b');

		$result = $field->validate();

		$this->assertConstraintValidationResultFailed('min', $result);
	}

	#[Test]
	public function it_validates_max_length_when_met(): void
	{
		$field = $this->createField()
			->maxLengthOf(5)
			->input('a@b');

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('max', $result);
	}

	#[Test]
	public function it_does_not_validate_max_length_when_not_met(): void
	{
		$field = $this->createField()
			->maxLengthOf(5)
			->input('user@domain');

		$result = $field->validate();

		$this->assertConstraintValidationResultFailed('max', $result);
	}

	#[Test]
	#[DataProvider('domains')]
	public function it_can_restrict_domains_passes_constraint(string $allowedDomain): void
	{
		$field = $this->createField()
			->allowDomain($allowedDomain)
			->input('user@' . $allowedDomain);

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('allowed_domains', $result);
	}

	#[Test]
	#[DataProvider('domains')]
	public function it_can_restrict_domains_fails_constraint(string $allowedDomain): void
	{
		$field = $this->createField()
			->allowDomain($allowedDomain)
			->input('user@example.net');

		$result = $field->validate();

		$this->assertConstraintValidationResultFailed('allowed_domains', $result);
	}

	#[Test]
	#[DataProvider('domains')]
	public function it_can_blacklist_domains_fails_constraint(string $disallowedDomain): void
	{
		$field = $this->createField()
			->disallowDomain($disallowedDomain)
			->input('user@' . $disallowedDomain);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailed('disallowed_domains', $result);
	}

	#[Test]
	#[DataProvider('domains')]
	public function it_can_blacklist_domains_passes_constraint(string $disallowedDomain): void
	{
		$field = $this->createField()
			->disallowDomain($disallowedDomain)
			->input('user@example.net');

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('allowed_domains', $result);
	}

	public static function domains(): array
	{
		return [
			'no wildcards or tld' => ['test'],
			'no wildcards or subdomains with tld' => ['test.com'],
			'no wildcards with tld and subdomain' => ['a.test.com'],
			'wildcard in subdomain' => ['*.test.com'],
			'wildcard in domain' => ['*.com'],
			'wildcard in domain and tld' => ['*.test.*'],
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

	#[Test]
	public function it_serializes_and_deserializes(): void
	{
		$sut = $this->createField()
			->minLengthOf(5)
			->maxLengthOf(128)
			->allowDomain('example.org')
			->disallowDomain('example.com')
			->prefill('postmaster@example.org');

		$serialized = $sut->serialize();

		$this->assertEquals('email_address', $serialized->type);
		$this->assertEquals('email_address', $serialized->name);
		$this->assertFalse($serialized->optional);
		$this->assertEquals('basic', $serialized->format);
		$this->assertEquals(5, $serialized->min);
		$this->assertEquals(128, $serialized->max);
		$this->assertEquals(['example.org'], $serialized->allowedDomains);
		$this->assertEquals(['example.com'], $serialized->disallowedDomains);
		$this->assertEquals('postmaster@example.org', $serialized->value);

		$deserialized = EmailAddress::deserialize($serialized);

		$this->assertEquals('email_address', $deserialized->type->value);
		$this->assertEquals('email_address', $deserialized->name->value);
		$this->assertFalse($deserialized->optional);
		$this->assertEquals('basic', $deserialized->format->value);
		$this->assertEquals(5, $deserialized->min);
		$this->assertEquals(128, $deserialized->max);
		$this->assertEquals(['example.org'], $deserialized->allowedDomains);
		$this->assertEquals(['example.com'], $deserialized->disallowedDomains);
		$this->assertEquals('postmaster@example.org', $deserialized->defaultValue->unwrap());
	}
}
