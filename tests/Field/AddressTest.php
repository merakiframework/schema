<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Address;
use Meraki\Schema\Field\Address\Type;
use Meraki\Schema\Field\Composite;
use Meraki\Schema\Property;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use InvalidArgumentException;

#[Group('field')]
#[CoversClass(Address::class)]
final class AddressTest extends CompositeTestCase
{
	private const AU = [
		'line1' => '1 Queen St',
		'locality' => 'Brisbane',
		'administrative_area' => 'QLD',
		'postal_code' => '4000',
	];

	public function createSubject(): Address
	{
		return new Address(new Property\Name('test'));
	}

	public function createField(): Address
	{
		return new Address(new Property\Name('test'));
	}

	#[Test]
	public function it_has_the_correct_name(): void
	{
		$this->assertSame('test', $this->createField()->name->value);
	}

	#[Test]
	public function it_is_a_composite_field(): void
	{
		$this->assertInstanceOf(Composite::class, $this->createField());
	}

	#[Test]
	public function it_has_no_value_by_default(): void
	{
		$this->assertEquals($this->emptyAddress(), $this->createSubject()->value->unwrap());
	}

	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$this->assertEquals($this->emptyAddress(), $this->createSubject()->defaultValue->unwrap());
	}

	#[Test]
	public function it_allows_no_countries_by_default(): void
	{
		$this->assertSame([], $this->createSubject()->allowed);
	}

	#[Test]
	public function it_is_of_type_either_by_default(): void
	{
		$this->assertSame(Type::Either, $this->createSubject()->type);
	}

	#[Test]
	public function it_rejects_a_country_that_does_not_exist(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new Address(new Property\Name('test'), ['ZZ']);
	}

	#[Test]
	public function it_accepts_a_country_in_any_case(): void
	{
		$this->assertSame(['AU'], (new Address(new Property\Name('test'), ['au']))->allowed);
	}

	// Free-form: no whitelist, so nothing but line1 is required and nothing is checked.

	#[Test]
	public function a_free_form_address_accepts_anything(): void
	{
		$field = $this->createSubject()->input([
			'line1' => 'somewhere over there',
			'locality' => 'Nowhere',
			'administrative_area' => 'Not A Real State',
			'postal_code' => 'not-a-postcode',
			'country_code' => 'AU',
		]);

		$this->assertFalse($field->validate()->anyFailed());
	}

	#[Test]
	public function a_free_form_address_needs_only_a_street_address(): void
	{
		$this->assertFalse($this->createSubject()->input(['line1' => 'somewhere'])->validate()->anyFailed());
	}

	#[Test]
	public function a_free_form_address_still_needs_a_street_address(): void
	{
		$this->assertTrue($this->createSubject()->input(['locality' => 'Brisbane'])->validate()->anyFailed());
	}

	// A single allowed country: its rules apply and the country itself is settled.

	#[Test]
	public function a_single_allowed_country_determines_the_country_field(): void
	{
		$field = new Address(new Property\Name('test'), ['AU']);

		$this->assertSame(['country_code'], $field->determined());
		$this->assertSame('AU', $field->countryCode->resolvedValue->unwrap());
		$this->assertSame('AU', $field->defaultValue->unwrap()['test.country_code']);
	}

	#[Test]
	public function a_determined_country_survives_a_prefill_that_omits_it(): void
	{
		$field = (new Address(new Property\Name('test'), ['AU']))->prefill(['line1' => '1 Queen St']);

		$this->assertSame('AU', $field->defaultValue->unwrap()['test.country_code']);
	}

	#[Test]
	public function a_single_allowed_country_offers_its_subdivisions_as_a_closed_set(): void
	{
		$field = new Address(new Property\Name('test'), ['AU']);

		$this->assertInstanceOf(Enum::class, $field->administrativeArea);
		$this->assertEqualsCanonicalizing(
			['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA'],
			$field->administrativeArea->oneOf,
		);
	}

	#[Test]
	public function it_accepts_a_well_formed_australian_address(): void
	{
		$field = new Address(new Property\Name('test'), ['AU']);

		$this->assertFalse($field->input(self::AU)->validate()->anyFailed());
	}

	#[Test]
	public function it_rejects_a_postcode_that_does_not_match_the_country(): void
	{
		$field = (new Address(new Property\Name('test'), ['AU']))->input(['postal_code' => '12345'] + self::AU);

		$this->assertConstraintValidationResultFailedForField('test.postal_code', 'test.postal_code.format', $field->validate());
	}

	#[Test]
	public function it_rejects_a_subdivision_the_country_does_not_have(): void
	{
		$field = (new Address(new Property\Name('test'), ['AU']))->input(['administrative_area' => 'XYZ'] + self::AU);

		$this->assertTrue($field->validate()->anyFailed());
	}

	#[Test]
	public function it_rejects_an_address_missing_a_part_the_country_requires(): void
	{
		$australia = self::AU;
		unset($australia['locality']);

		$field = (new Address(new Property\Name('test'), ['AU']))->input($australia);

		$this->assertTrue($field->validate()->anyFailed());
	}

	/**
	 * Not every country uses every part. Singapore has no administrative areas at all and
	 * Hong Kong has no postcodes, so requiring either would make those addresses
	 * impossible to submit.
	 */
	#[Test]
	#[DataProvider('addressesFromCountriesWithMissingParts')]
	public function it_only_requires_the_parts_a_country_actually_uses(string $country, array $address): void
	{
		$field = (new Address(new Property\Name('test'), [$country]))->input($address);

		$this->assertFalse($field->validate()->anyFailed());
	}

	public static function addressesFromCountriesWithMissingParts(): array
	{
		return [
			'Singapore has no administrative area' => ['SG', [
				'line1' => '1 Raffles Place',
				'locality' => 'Singapore',
				'postal_code' => '048616',
			]],
			'Hong Kong has no postal code' => ['HK', [
				'line1' => '1 Nathan Road',
				'administrative_area' => 'Kowloon',
			]],
		];
	}

	// Several allowed countries: the country becomes a choice and drives the rules.

	#[Test]
	public function several_allowed_countries_leave_the_country_to_be_chosen(): void
	{
		$field = new Address(new Property\Name('test'), ['AU', 'NZ']);

		$this->assertSame([], $field->determined());
		$this->assertInstanceOf(Enum::class, $field->countryCode);
		$this->assertEqualsCanonicalizing(['AU', 'NZ'], $field->countryCode->oneOf);
	}

	/**
	 * Which subdivisions are valid depends on the country chosen, so with several allowed
	 * there is no closed set to offer and it stays free text — checked server-side.
	 */
	#[Test]
	public function several_allowed_countries_leave_the_subdivision_as_free_text(): void
	{
		$field = new Address(new Property\Name('test'), ['AU', 'NZ']);

		$this->assertInstanceOf(Text::class, $field->administrativeArea);
	}

	#[Test]
	public function it_validates_against_whichever_allowed_country_was_chosen(): void
	{
		$field = (new Address(new Property\Name('test'), ['AU', 'NZ']))->input([
			'line1' => '1 Queen St',
			'locality' => 'Auckland',
			'administrative_area' => 'AUK',
			'postal_code' => '1010',
			'country_code' => 'NZ',
		]);

		$this->assertFalse($field->validate()->anyFailed());
	}

	#[Test]
	public function it_rejects_a_subdivision_belonging_to_a_different_allowed_country(): void
	{
		// AUK is a New Zealand region, so it is not valid for an Australian address.
		$field = (new Address(new Property\Name('test'), ['AU', 'NZ']))->input([
			'administrative_area' => 'AUK',
			'country_code' => 'AU',
		] + self::AU);

		$this->assertConstraintValidationResultFailedForField(
			'test.administrative_area',
			'test.administrative_area.allowed',
			$field->validate(),
		);
	}

	/**
	 * One wrong country should be reported once, on the country field — not repeated as
	 * postcode and subdivision failures derived from a country we already know is wrong.
	 */
	#[Test]
	public function a_country_outside_the_whitelist_fails_only_the_country_field(): void
	{
		$field = (new Address(new Property\Name('test'), ['AU', 'NZ']))->input(['country_code' => 'US'] + self::AU);

		$result = $field->validate();

		$this->assertConstraintValidationResultFailedForField('test.country_code', 'type', $result);
		$this->assertConstraintValidationResultSkippedForField('test.postal_code', 'test.postal_code.format', $result);
		$this->assertConstraintValidationResultSkippedForField('test.administrative_area', 'test.administrative_area.allowed', $result);
	}

	#[Test]
	public function it_normalises_the_country_to_upper_case(): void
	{
		$field = (new Address(new Property\Name('test'), ['AU', 'NZ']))->input(['country_code' => 'au'] + self::AU);

		$this->assertFalse($field->validate()->anyFailed());
	}

	// Configuring after construction.

	#[Test]
	public function allowing_a_country_later_applies_the_same_rules(): void
	{
		$field = $this->createSubject();
		$field->allow('AU');

		$this->assertSame(['country_code'], $field->determined());
		$this->assertTrue($field->input(['postal_code' => '12345'] + self::AU)->validate()->anyFailed());
	}

	// Address type.

	#[Test]
	#[DataProvider('poBoxExpectations')]
	public function it_only_rejects_a_po_box_when_the_address_must_be_visitable(Type $type, string $line1, bool $expectedToFail): void
	{
		$field = (new Address(new Property\Name('test'), ['AU']))
			->ofType($type)
			->input(['line1' => $line1] + self::AU);

		$this->assertSame($expectedToFail, $field->validate()->anyFailed());
	}

	public static function poBoxExpectations(): array
	{
		return [
			'either accepts a po box' => [Type::Either, 'PO Box 123', false],
			'postal accepts a po box' => [Type::Postal, 'PO Box 123', false],
			'physical rejects a po box' => [Type::Physical, 'PO Box 123', true],
			'both rejects a po box' => [Type::Both, 'PO Box 123', true],
			'physical rejects a gpo box' => [Type::Physical, 'GPO Box 9', true],
			'physical rejects a locked bag' => [Type::Physical, 'Locked Bag 4000', true],
			'physical accepts a street address' => [Type::Physical, '1 Queen St', false],
			'physical accepts a street that merely starts with p' => [Type::Physical, 'Post Office Lane', false],
		];
	}

	/** @return array<string, null> */
	private function emptyAddress(): array
	{
		return [
			'test.organization' => null,
			'test.line1' => null,
			'test.line2' => null,
			'test.dependent_locality' => null,
			'test.locality' => null,
			'test.administrative_area' => null,
			'test.postal_code' => null,
			'test.country_code' => null,
		];
	}
}
