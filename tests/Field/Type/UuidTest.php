<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Type;

use Meraki\Schema\Field\Type\Uuid;
use Meraki\Schema\Attribute;
use Meraki\Schema\Constraint;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\{Test, CoversClass, DataProvider};

#[CoversClass(Uuid::class)]
final class UuidTest extends TestCase
{
	#[Test]
	public function it_exists(): void
	{
		$uuid = $this->createField();

		$this->assertInstanceOf(Uuid::class, $uuid);
	}

	#[Test]
	#[DataProvider('validUuids')]
	public function it_validates_valid_uuids_without_version_constraint(string $uuid): void
	{
		$field = $this->createField()->input($uuid);

		$this->assertTrue($field->validationResult->passed());
	}

	#[Test]
	#[DataProvider('invalidUuids')]
	public function it_does_not_validate_invalid_uuids_without_version_constraint(string $uuid): void
	{
		$field = $this->createField()->input($uuid);

		$this->assertTrue($field->validationResult->failed());
	}

	#[Test]
	public function it_passes_when_only_allowing_version_1_uuids(): void
	{
		$field = $this->createField()
			->accept(1)
			->input('C232AB00-9414-11EC-B3C8-9F6BDECED846');

		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Version::class);
	}

	#[Test]
	public function it_fails_when_input_is_not_version_1_uuid(): void
	{
		$field = $this->createField()
			->accept(1)
			->input('d94e3f0e-1b3b-21ec-82a8-0242ac130003');

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Version::class);
	}

	#[Test]
	public function it_passes_when_only_allowing_version_2_uuids(): void
	{
		$field = $this->createField()
			->accept(2)
			->input('d94e3f0e-1b3b-21ec-82a8-0242ac130003');

		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Version::class);
	}

	#[Test]
	public function it_fails_when_input_is_not_version_2_uuid(): void
	{
		$field = $this->createField()
			->accept(2)
			->input('C232AB00-9414-11EC-B3C8-9F6BDECED846');

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Version::class);
	}

	#[Test]
	public function it_passes_when_only_allowing_version_3_uuids(): void
	{
		$field = $this->createField()
			->accept(3)
			->input('5df41881-3aed-3515-88a7-2f4a814cf09e');

		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Version::class);
	}

	#[Test]
	public function it_fails_when_input_is_not_version_3_uuid(): void
	{
		$field = $this->createField()
			->accept(3)
			->input('d94e3f0e-1b3b-21ec-82a8-0242ac130003');

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Version::class);
	}

	#[Test]
	public function it_passes_when_only_allowing_version_4_uuids(): void
	{
		$field = $this->createField()
			->accept(4)
			->input('919108f7-52d1-4320-9bac-f847db4148a8');

		$this->assertTrue($field->validationResult->allPassed());
		$this->assertValidationPassedForConstraint($field, Attribute\Version::class);
	}

	#[Test]
	public function it_fails_when_input_is_not_version_4_uuid(): void
	{
		$field = $this->createField()
			->accept(4)
			->input('d94e3f0e-1b3b-21ec-82a8-0242ac130003');

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Version::class);
	}

	#[Test]
	public function it_passes_when_only_allowing_version_5_uuids(): void
	{
		$field = $this->createField()
			->accept(5)
			->input('2ed6657d-e927-568b-95e1-2665a8aea6a2');

		$this->assertTrue($field->validationResult->allPassed());
		$this->assertValidationPassedForConstraint($field, Attribute\Version::class);
	}

	#[Test]
	public function it_fails_when_input_is_not_version_5_uuid(): void
	{
		$field = $this->createField()
			->accept(5)
			->input('d94e3f0e-1b3b-21ec-82a8-0242ac130003');

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Version::class);
	}

	#[Test]
	public function it_passes_when_only_allowing_version_6_uuids(): void
	{
		$field = $this->createField()
			->accept(6)
			->input('1EC9414C-232A-6B00-B3C8-9F6BDECED846');

		$this->assertTrue($field->validationResult->allPassed());
		$this->assertValidationPassedForConstraint($field, Attribute\Version::class);
	}

	#[Test]
	public function it_fails_when_input_is_not_version_6_uuid(): void
	{
		$field = $this->createField()
			->accept(6)
			->input('d94e3f0e-1b3b-21ec-82a8-0242ac130003');

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Version::class);
	}

	#[Test]
	public function it_passes_when_only_allowing_version_7_uuids(): void
	{
		$field = $this->createField()
			->accept(7)
			->input('017F22E2-79B0-7CC3-98C4-DC0C0C07398F');

		$this->assertTrue($field->validationResult->allPassed());
		$this->assertValidationPassedForConstraint($field, Attribute\Version::class);
	}

	#[Test]
	public function it_fails_when_input_is_not_version_7_uuid(): void
	{
		$field = $this->createField()
			->accept(7)
			->input('d94e3f0e-1b3b-21ec-82a8-0242ac130003');

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Version::class);
	}

	#[Test]
	public function it_passes_when_only_allowing_version_8_uuids(): void
	{
		$field = $this->createField()
			->accept(8)
			->input('2489E9AD-2EE2-8E00-8EC9-32D5F69181C0');

		$this->assertTrue($field->validationResult->allPassed());
		$this->assertValidationPassedForConstraint($field, Attribute\Version::class);
	}

	#[Test]
	public function it_fails_when_input_is_not_version_8_uuid(): void
	{
		$field = $this->createField()
			->accept(8)
			->input('d94e3f0e-1b3b-21ec-82a8-0242ac130003');

		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Version::class);
	}

	#[Test]
	public function it_can_accept_multiple_versions(): void
	{
		$field = $this->createField()->accept(4)->accept(7);

		// version 4 test
		$field->input('919108f7-52d1-4320-9bac-f847db4148a8');
		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Version::class);

		// version 7 test
		$field->input('017F22E2-79B0-7CC3-98C4-DC0C0C07398F');
		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Version::class);

		// version 3 test
		$field->input('5df41881-3aed-3515-88a7-2f4a814cf09e');
		$this->assertTrue($field->validationResult->failed());
		$this->assertValidationFailedForConstraint($field, Attribute\Version::class);
	}

	#[Test]
	public function it_allows_any_version_when_no_version_constraint_is_set(): void
	{
		$field = $this->createField()
			->input('d94e3f0e-1b3b-21ec-82a8-0242ac130003');

		$this->assertTrue($field->validationResult->passed());
		$this->assertValidationPassedForConstraint($field, Attribute\Version::class);
	}

	public function createField(): Uuid
	{
		return new Uuid(new Attribute\Name('user_id'));
	}

	public function getExpectedType(): string
	{
		return 'uuid';
	}

	public function getValidValue(): mixed
	{
		return 'd94e3f0e-1b3b-21ec-82a8-0242ac130003';
	}

	public function getInvalidValue(): mixed
	{
		return '123';
	}

	public function usesConstraints(): bool
	{
		return true;
	}

	public function createValidConstraint(): Constraint
	{
		return new Attribute\Version();
	}

	public function createInvalidConstraint(): Constraint
	{
		return new Attribute\Version([7]);
	}

	public static function invalidUuids(): array
	{
		return [
			'empty string' => [''],
			'missing dashes' => ['d94e3f0e1b3b21ec82a80242ac130003'],
			'extra dashes' => ['C232AB00-9414--11EC-B3C8-9F6BDECED846'],
			'invalid separators' => ['017F22E2 79B0 7CC3 98C4 DC0C0C07398F'],
			'contains non hex chars' => ['5Zf41881-3aed-3515-88a7-2f4Y814cf09e'],
			'version not in right place' => ['919108f7-52d1-f720-9bac-f847db4148a8'],
		];
	}

	public static function validUuids(): array
	{
		// source: https://datatracker.ietf.org/doc/html/rfc9562#name-test-vectors
		return [
			'nil uuid' => ['00000000-0000-0000-0000-000000000000'],
			'version 1 uuid' => ['C232AB00-9414-11EC-B3C8-9F6BDECED846'],
			'version 2 uuid' => ['d94e3f0e-1b3b-21ec-82a8-0242ac130003'],
			'version 3 uuid' => ['5df41881-3aed-3515-88a7-2f4a814cf09e'],
			'version 4 uuid' => ['919108f7-52d1-4320-9bac-f847db4148a8'],
			'version 5 uuid' => ['2ed6657d-e927-568b-95e1-2665a8aea6a2'],
			'version 6 uuid' => ['1EC9414C-232A-6B00-B3C8-9F6BDECED846'],
			'version 7 uuid' => ['017F22E2-79B0-7CC3-98C4-DC0C0C07398F'],
			'version 8 uuid' => ['2489E9AD-2EE2-8E00-8EC9-32D5F69181C0'],
			'max uuid' => ['ffffffff-ffff-ffff-ffff-ffffffffffff'],
		];
	}
}
