<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;


use Meraki\Schema\Field\PhoneNumber;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass, DataProvider};

#[CoversClass(PhoneNumber::class)]
final class PhoneNumberTest extends FieldTestCase
{
	#[Test]
	public function it_exists(): void
	{
		$boolean = $this->createField();

		$this->assertInstanceOf(PhoneNumber::class, $boolean);
	}

	public function createField(): PhoneNumber
	{
		return new PhoneNumber(new Attribute\Name('phone_number'));
	}

	#[Test]
	#[DataProvider('validPhoneNumbers')]
	public function it_validates_valid_phone_numbers(string $phoneNumber): void
	{
		$field = $this->createField()
			->input($phoneNumber);

		$this->assertTrue($field->validationResult->passed());
	}

	#[Test]
	#[DataProvider('invalidPhoneNumbers')]
	public function it_does_not_validate_invalid_phone_numbers(string $phoneNumber): void
	{
		$field = $this->createField()
			->input($phoneNumber);

		$this->assertTrue($field->validationResult->failed());
	}

	public static function validPhoneNumbers(): array
	{
		return [
			'with minimum length no spaces' => ['+61'],
			'with maximum length no spaces' => ['+613123456789012'],
			'with minimum length with spaces' => ['+6 3'],
			'with maximum length with spaces' => ['+61 3 1234 5678 9012'],
			'with parentheses' => ['+61 (3) 1234 5678'],
			'with hyphens' => ['+61-3-1234-5678'],
			'with periods' => ['+61.3.1234.5678'],
			'with mixed separators' => ['+61 (3) 1234-5678'],
			'with mixed separators at max length' => ['+61 (3) 1234 5678 9012'],
		];
	}

	public static function invalidPhoneNumbers(): array
	{
		return [
			'missing "+" prefix' => ['61 3 1234 5678'],
			'with invalid characters' => ['+61 3 1234 5678a'],
			'too short' => ['+6'],
			'too long' => ['+6131234567890123'],
			'with invalid separators' => ['+61/3/1234/5678'],
			'too long with separators' => ['+61 (3) 1234 5678 90123'],
			'contains formatting characters proceeding country code' => ['+ 61 3 1234 5678'],
			'contains formatting characters around country code' => ['+(61) 3 1234 5678'],
		];
	}

	public function usesConstraints(): bool
	{
		return false;
	}

	public function getExpectedType(): string
	{
		return 'phone_number';
	}

	public function getValidValue(): string
	{
		return '+61 3 1234 5678';
	}

	public function getInvalidValue(): string
	{
		return '1234 5678';
	}

	public function createValidConstraint(): Constraint
	{
		return new Attribute\Max(5);
	}

	public function createInvalidConstraint(): Constraint
	{
		return new Attribute\Max(1);
	}
}
