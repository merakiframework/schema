<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Uuid;
use Meraki\Schema\Property;
use Meraki\Schema\ValidationStatus;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[Group('field')]
#[CoversClass(Uuid::class)]
final class UuidTest extends FieldTestCase
{
	public function createSubject(): Uuid
	{
		return new Uuid(new Property\Name('uuid'));
	}

	public function createField(): Uuid
	{
		return $this->createSubject();
	}

	#[Test]
	#[DataProvider('validUuids')]
	public function it_accepts_valid_uuids(string $uuid): void
	{
		$sut = $this->createSubject()
			->input($uuid);

		$result = $sut->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
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

	#[Test]
	#[DataProvider('invalidUuids')]
	public function it_rejects_invalid_uuids(string $uuid): void
	{
		$sut = $this->createSubject()
			->input($uuid);

		$result = $sut->validate();

		$this->assertConstraintValidationResultFailed('type', $result);
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

	#[Test]
	#[DataProvider('restrictedUuidsThatShouldPass')]
	public function uuids_can_be_restricted_to_a_version_and_pass(int $version, string $uuidToPass): void
	{
		$sut = $this->createSubject()
			->input($uuidToPass)
			->restrictToVersion($version);

		$result = $sut->validate();

		$this->assertConstraintValidationResultPassed('version', $result);
	}

	public static function restrictedUuidsThatShouldPass(): array
	{
		return [
			'Null UUID' => [0, '00000000-0000-0000-0000-000000000000'],
			'Version 1 UUID' => [1, 'C232AB00-9414-11EC-B3C8-9F6BDECED846'],
			'Version 2 UUID' => [2, 'd94e3f0e-1b3b-21ec-82a8-0242ac130003'],
			'Version 3 UUID' => [3, '5df41881-3aed-3515-88a7-2f4a814cf09e'],
			'Version 4 UUID' => [4, '919108f7-52d1-4320-9bac-f847db4148a8'],
			'Version 5 UUID' => [5, '2ed6657d-e927-568b-95e1-2665a8aea6a2'],
			'Version 6 UUID' => [6, '1EC9414C-232A-6B00-B3C8-9F6BDECED846'],
			'Version 7 UUID' => [7, '017F22E2-79B0-7CC3-98C4-DC0C0C07398F'],
			'Version 8 UUID' => [8, '2489E9AD-2EE2-8E00-8EC9-32D5F69181C0'],
			'All bits set UUID' => [-1, 'ffffffff-ffff-ffff-ffff-ffffffffffff'],
		];
	}

	#[Test]
	#[DataProvider('restrictedUuidsThatShouldFail')]
	public function uuids_can_be_restricted_to_a_version_and_fail(int $version, string $uuidToFail): void
	{
		$sut = $this->createSubject()
			->input($uuidToFail)
			->restrictToVersion($version);

		$result = $sut->validate();

		$this->assertConstraintValidationResultFailed('version', $result);
	}

	public static function restrictedUuidsThatShouldFail(): array
	{
		return [
			'Null UUID' => [0, 'd94e3f0e-1b3b-21ec-82a8-0242ac130003'],
			'Version 1 UUID' => [1, 'd94e3f0e-1b3b-21ec-82a8-0242ac130003'],
			'Version 2 UUID' => [2, 'ffffffff-ffff-ffff-ffff-ffffffffffff'],
			'Version 3 UUID' => [3, 'd94e3f0e-1b3b-21ec-82a8-0242ac130003'],
			'Version 4 UUID' => [4, 'd94e3f0e-1b3b-21ec-82a8-0242ac130003'],
			'Version 5 UUID' => [5, 'd94e3f0e-1b3b-21ec-82a8-0242ac130003'],
			'Version 6 UUID' => [6, 'd94e3f0e-1b3b-21ec-82a8-0242ac130003'],
			'Version 7 UUID' => [7, 'd94e3f0e-1b3b-21ec-82a8-0242ac130003'],
			'Version 8 UUID' => [8, 'd94e3f0e-1b3b-21ec-82a8-0242ac130003'],
			'All bits set UUID' => [-1, '00000000-0000-0000-0000-000000000000'],
		];
	}

	#[Test]
	#[DataProvider('restrictedVersions')]
	public function can_restrict_to_mulitple_versions(string $uuid, ValidationStatus $expectedStatus): void
	{
		$sut = $this->createSubject()
			->input($uuid)
			->restrictToVersion(4)
			->restrictToVersion(7);

		$result = $sut->validate();

		$this->assertConstraintValidationResultHasStatusOf($expectedStatus, 'version', $result);
	}

	public static function restrictedVersions(): array
	{
		return [
			'version 4' => ['919108f7-52d1-4320-9bac-f847db4148a8', ValidationStatus::Passed],
			'version 7' => ['017F22E2-79B0-7CC3-98C4-DC0C0C07398F', ValidationStatus::Passed],
			'version 3' => ['5df41881-3aed-3515-88a7-2f4a814cf09e', ValidationStatus::Failed],
		];
	}

	#[Test]
	public function it_has_no_value_by_default(): void
	{
		$sut = $this->createSubject();

		$this->assertNull($sut->value->unwrap());
	}

	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$sut = $this->createSubject();

		$this->assertNull($sut->defaultValue->unwrap());
	}

	#[Test]
	public function it_serializes_and_deserializes(): void
	{
		$uuid = '919108f7-52d1-4320-9bac-f847db4148a8';
		$sut = $this->createField()
			->makeOptional()
			->restrictToVersion(4)
			->prefill($uuid);

		$serialized = $sut->serialize();

		// serializing normalises time strings
		$this->assertEquals('uuid', $serialized->type);
		$this->assertEquals('uuid', $serialized->name);
		$this->assertTrue($serialized->optional);
		$this->assertEquals([4], $serialized->versions);
		$this->assertEquals($uuid, $serialized->value);

		$deserialized = Uuid::deserialize($serialized);

		$this->assertEquals('uuid', $deserialized->type->value);
		$this->assertEquals('uuid', $deserialized->name->value);
		$this->assertTrue($deserialized->optional);
		$this->assertEquals([4], $deserialized->versions);
		$this->assertEquals($uuid, $deserialized->defaultValue->unwrap());
	}
}
