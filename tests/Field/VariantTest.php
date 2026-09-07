<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Property;
use Meraki\Schema\Field\Variant as VariantField;
use Meraki\Schema\FieldTestCase;
use Meraki\Schema\ValidationStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use InvalidArgumentException;

#[Group('field')]
#[CoversClass(VariantField::class)]
final class VariantTest extends FieldTestCase
{
	public function createSubject(): VariantField
	{
		return new VariantField(
			new Property\Name('secret'),
			new Field\Passphrase(new Property\Name('passphrase')),
			new Field\Password(new Property\Name('password')),
		);
	}

	public function createField(): VariantField
	{
		return $this->createSubject();
	}

	#[Test]
	public function it_has_a_name(): void
	{
		$sut = $this->createSubject();

		$this->assertInstanceOf(Property\Name::class, $sut->name);
		$this->assertEquals('secret', (string) $sut->name);
	}
	#[Test]
	public function it_prefixes_field_names_correctly(): void
	{
		$sut = $this->createSubject();

		$this->assertEquals('secret.passphrase', (string) $sut->passphrase->name);
		$this->assertEquals('secret.password', (string) $sut->password->name);
	}

	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$sut = $this->createSubject();

		$this->assertNull($sut->defaultValue->unwrap());
	}


	#[Test]
	public function it_throws_when_duplicate_field_types_are_added(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Variant fields cannot contain duplicate field types.");

		$sut = new VariantField(
			new Property\Name('secret'),
			new Field\Passphrase(new Property\Name('passphrase1')),
			new Field\Passphrase(new Property\Name('passphrase2')),
		);
	}

	#[Test]
	public function it_returns_failed_validation_when_no_value_is_provided_and_field_is_required(): void
	{
		$sut = $this->createSubject();

		$result = $sut->validate(null);

		$this->assertConstraintValidationResultFailed('type', $result);
	}

	#[Test]
	public function it_returns_skipped_validation_when_no_value_is_provided_and_field_is_optional(): void
	{
		$sut = $this->createSubject()->makeOptional();

		$result = $sut->validate(null);

		$this->assertConstraintValidationResultSkipped('type', $result);
	}

	#[Test]
	public function it_uses_the_first_matching_field_for_validation(): void
	{
		$sut = $this->createSubject();

		$result = $sut->validate('correct horse battery staple');

		$this->assertEquals(ValidationStatus::Passed, $result->status);
		$this->assertInstanceOf(Field\Passphrase::class, $result->field);
	}

	#[Test]
	public function it_uses_the_second_matching_field_if_the_first_does_not_match(): void
	{
		$sut = $this->createSubject();

		$result = $sut->validate('password');

		$this->assertEquals(ValidationStatus::Passed, $result->status);
		$this->assertInstanceOf(Field\Password::class, $result->field);
	}

	#[Test]
	public function it_fails_validation_if_no_fields_match(): void
	{
		$sut = new VariantField(
			new Property\Name('secret'),
			Field\Passphrase::paranoid(new Property\Name('passphrase')),
			Field\Password::strong(new Property\Name('password')),
		);

		$result = $sut->validate('x');

		$this->assertEquals(ValidationStatus::Failed, $result->status);
		$this->assertTrue($result->allFailed());
	}

	#[Test]
	public function it_resolves_to_the_matched_fields_value(): void
	{
		$input = 'correct horse battery staple'; // valid passphrase

		$result = $this->createSubject()->validate($input);

		$this->assertEquals($input, $result->value);
	}

	#[Test]
	public function it_prefills_value_to_fields_correctly(): void
	{
		$value = 'correct horse battery staple';
		$sut = $this->createSubject()->prefill($value);

		$this->assertEquals($value, $sut->passphrase->defaultValue->unwrap());
		$this->assertEquals($value, $sut->password->defaultValue->unwrap());
	}

}
