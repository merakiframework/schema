<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Address\Precision;
use Meraki\Schema\Field\Address\Value;
use Meraki\Schema\FieldName;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use InvalidArgumentException;

/**
 * The field's half of an address: what it demands, and what it reports when the demand is unmet.
 *
 * The value object's half — what an address *is*, and the three ways it can fail to be one — is
 * `Address\ValueTest`. What a country asks for is `Address\RequirementsTest`. The ladder itself is
 * `Address\PrecisionTest`.
 *
 * Two dials replaced a four-case enum here. The enum came from HL7 FHIR, where
 * `postal | physical | both` describes an address someone already holds rather than demanding
 * anything of a submitter — which is why `Postal` had nothing to do at request time. Depth and
 * attendability are independent questions, so they are independent dials, and every combination
 * of them is legal.
 */
#[Group('field')]
#[CoversClass(Address::class)]
#[CoversClass(Value::class)]
final class AddressTest extends FieldTestCase
{
	public function createField(): Address
	{
		return new Address(new FieldName('billing'));
	}

	private function australian(): Address
	{
		return new Address(new FieldName('billing'), ['AU']);
	}

	/** @return array<string, mixed> */
	private static function rockhampton(string ...$without): array
	{
		return array_diff_key([
			'street' => ['1 Denham St'],
			'locality' => 'Rockhampton',
			'subdivision' => 'QLD',
			'postal_code' => '4700',
			'country' => 'AU',
		], array_flip($without));
	}

	/** @param array<string, mixed> $overrides */
	private static function au(array $overrides = [], string ...$without): object
	{
		return (object) array_merge(self::rockhampton(...$without), $overrides);
	}

	// ── one field, one value ───────────────────────────────────────────────────────────────

	#[Test]
	public function it_holds_its_whole_value_rather_than_a_bag_of_sub_fields(): void
	{
		$this->assertFalse(property_exists($this->australian(), 'fields'));
	}

	#[Test]
	public function it_accepts_the_record_a_form_submits(): void
	{
		$resolved = $this->australian()->resolve(self::au());

		$this->assertInstanceOf(Value::class, $resolved->value);
		$this->assertSame(['1 Denham St'], $resolved->value->street);
	}

	#[Test]
	public function it_accepts_its_own_value_object(): void
	{
		$value = Value::of(street: ['1 Denham St'], locality: 'Rockhampton', subdivision: 'QLD', postalCode: '4700', country: 'AU');

		$this->assertSame($value, $this->australian()->resolve($value)->value);
	}

	#[Test]
	public function an_address_is_a_record_and_not_a_list(): void
	{
		$this->assertTrue($this->australian()->validate(['street' => ['1 Denham St']])->shape->wasUnreadable());
	}

	#[Test]
	public function every_constraint_names_the_part_it_is_about(): void
	{
		$expected = [
			'allowedCountries' => 'country',
			'streetRequired' => 'street',
			'streetLineLimit' => 'street',
			'streetVisitable' => 'street',
			'localityRequired' => 'locality',
			'localityUsed' => 'locality',
			'dependentLocalityUsed' => 'dependent_locality',
			'subdivisionRequired' => 'subdivision',
			'subdivisionUsed' => 'subdivision',
			'knownSubdivision' => 'subdivision',
			'postalCodeRequired' => 'postal_code',
			'postalCodeUsed' => 'postal_code',
			'postalCodeFormat' => 'postal_code',
		];

		$result = $this->australian()->validate(self::au());

		foreach ($expected as $name => $part) {
			$this->assertSame($part, $result->forConstraint($name)->part?->value, $name);
		}
	}

	#[Test]
	public function a_constraint_name_carries_neither_the_field_name_nor_a_dot(): void
	{
		foreach ($this->australian()->constraints->names as $name) {
			$this->assertStringNotContainsString('.', $name);
			$this->assertStringNotContainsString('billing', $name);
		}
	}

	// ── the regression: requiredness was never checked ─────────────────────────────────────

	#[Test]
	public function an_australian_address_needs_a_suburb_a_state_and_a_postcode(): void
	{
		// This passed before the country's own rules were read: four of five constraints skipped
		// and the address came back valid with nothing but a street and a country.
		$result = $this->australian()->validate(self::au([], 'locality', 'subdivision', 'postal_code'));

		$this->assertTrue($result->forConstraint('localityRequired')->failed());
		$this->assertTrue($result->forConstraint('subdivisionRequired')->failed());
		$this->assertTrue($result->forConstraint('postalCodeRequired')->failed());
	}

	#[Test]
	public function a_complete_address_passes(): void
	{
		$this->assertFalse($this->australian()->validate(self::au())->anyFailed());
	}

	/**
	 * Each country asks for what its own format says, and a part it does not ask for is skipped
	 * rather than passed — so a message pack never has to explain a check that never applied.
	 *
	 * @return array<string, array{string, array<string, mixed>, list<string>, list<string>}>
	 */
	public static function countries(): array
	{
		return [
			'Japan addresses by prefecture, not locality' => [
				'JP',
				['street' => ['1-1 Chiyoda'], 'subdivision' => 'JP-13', 'postal_code' => '100-0001', 'country' => 'JP'],
				['streetRequired', 'subdivisionRequired', 'postalCodeRequired'],
				['localityRequired'],
			],
			'Panama has no postcode at all' => [
				'PA',
				['street' => ['Calle 50'], 'locality' => 'Ciudad de Panama', 'country' => 'PA'],
				['streetRequired', 'localityRequired'],
				['postalCodeRequired'],
			],
			'Great Britain has no subdivision' => [
				'GB',
				['street' => ['10 Downing St'], 'locality' => 'London', 'postal_code' => 'SW1A 2AA', 'country' => 'GB'],
				['streetRequired', 'localityRequired', 'postalCodeRequired'],
				['subdivisionRequired'],
			],
			'the Emirates require neither locality nor postcode' => [
				'AE',
				['street' => ['Sheikh Zayed Rd'], 'subdivision' => 'AE-DU', 'country' => 'AE'],
				['streetRequired', 'subdivisionRequired'],
				['localityRequired', 'postalCodeRequired'],
			],
			// Hong Kong codes its subdivisions by *name*, so its canonical ISO 3166-2 form is
			// `HK-Kowloon`. Leaving it out of this table is how the library came to reject the
			// very code it publishes for Hong Kong.
			'Hong Kong addresses by area, with no postcode' => [
				'HK',
				['street' => ['1 Queen\'s Rd'], 'subdivision' => 'HK-Kowloon', 'country' => 'HK'],
				['streetRequired', 'subdivisionRequired'],
				['postalCodeRequired'],
			],
			'the United States require all four' => [
				'US',
				['street' => ['1600 Pennsylvania Ave NW'], 'locality' => 'Washington', 'subdivision' => 'US-DC', 'postal_code' => '20500', 'country' => 'US'],
				['streetRequired', 'localityRequired', 'subdivisionRequired', 'postalCodeRequired'],
				[],
			],
		];
	}

	#[Test]
	public function a_country_may_ask_for_nothing_below_itself(): void
	{
		// Four of the 206 require only an address line, so dropping the street tier empties
		// their required set entirely. That is honest rather than degenerate: Antigua's format
		// genuinely has nothing between the country and the street.
		$field = $this->createField()->minPrecisionOf(Precision::Locality);

		foreach (['AG', 'GI', 'MO', 'VG'] as $country) {
			$result = $field->validate((object) ['country' => $country]);

			$this->assertFalse($result->anyFailed(), "{$country} should require nothing but a country");
		}
	}

	#[Test]
	public function the_twelve_countries_with_a_dependent_locality_accept_one(): void
	{
		// The counterpart to Australia rejecting one. `dependentLocalityUsed` must not refuse
		// a part a country genuinely has.
		$field = $this->createField()->minPrecisionOf(Precision::Country);

		foreach (['BR', 'CN', 'IE', 'IR', 'KR', 'MX', 'MY', 'NG', 'NZ', 'PH', 'TH', 'ZA'] as $country) {
			$result = $field->validate((object) [
				'dependent_locality' => 'Somewhere',
				'country' => $country,
			]);

			$this->assertTrue(
				$result->forConstraint('dependentLocalityUsed')->passed(),
				"{$country} uses a dependent locality and should accept one",
			);
		}
	}

	#[Test]
	public function a_country_whose_subdivisions_carry_their_own_postcode_pattern(): void
	{
		// China and Colombia are the only two. An unresolvable subdivision there used to make
		// the whole address unreadable, on the grounds that the postcode became undecidable.
		// It does not: the country's own pattern is still there to fall back on, so the bad
		// subdivision is reported as a bad subdivision and the postcode is still judged.
		$field = $this->createField();

		$valid = $field->validate((object) [
			'street' => ['1 Nanjing Rd'],
			'locality' => 'Shanghai Shi',
			'subdivision' => 'CN-SH',
			'postal_code' => '200000',
			'country' => 'CN',
		]);

		$this->assertTrue($valid->forConstraint('knownSubdivision')->passed());

		$unknown = $field->validate((object) [
			'street' => ['1 Nanjing Rd'],
			'locality' => 'Shanghai Shi',
			'subdivision' => 'Banana',
			'postal_code' => '200000',
			'country' => 'CN',
		]);

		$this->assertTrue($unknown->shape->passed());
		$this->assertTrue($unknown->forConstraint('knownSubdivision')->failed());
		$this->assertTrue($unknown->forConstraint('postalCodeFormat')->passed());

		$badPostcode = $field->validate((object) [
			'street' => ['1 Nanjing Rd'],
			'locality' => 'Shanghai Shi',
			'subdivision' => 'Banana',
			'postal_code' => 'zzz',
			'country' => 'CN',
		]);

		$this->assertTrue($badPostcode->forConstraint('postalCodeFormat')->failed());
	}

	#[Test]
	#[DataProvider('countries')]
	public function it_asks_each_country_what_that_country_requires(
		string $country,
		array $address,
		array $asked,
		array $notAsked,
	): void {
		$result = (new Address(new FieldName('billing'), [$country]))->validate((object) $address);

		foreach ($asked as $constraint) {
			$this->assertTrue($result->forConstraint($constraint)->passed(), "{$country}: {$constraint} should have been asked");
		}

		foreach ($notAsked as $constraint) {
			$this->assertTrue($result->forConstraint($constraint)->skipped(), "{$country}: {$constraint} should have skipped");
		}
	}

	// ── the precision ladder ───────────────────────────────────────────────────────────────

	/** @return array<string, array{Precision, list<string>}> */
	public static function floors(): array
	{
		return [
			'street' => [Precision::Street, ['street', 'locality', 'subdivision', 'postal_code']],
			'locality' => [Precision::Locality, ['locality', 'subdivision', 'postal_code']],
			'subdivision' => [Precision::Subdivision, ['subdivision']],
			'country' => [Precision::Country, []],
		];
	}

	#[Test]
	#[DataProvider('floors')]
	public function the_floor_decides_which_requirements_are_asked(Precision $floor, array $required): void
	{
		$all = ['street' => 'streetRequired', 'locality' => 'localityRequired', 'subdivision' => 'subdivisionRequired', 'postal_code' => 'postalCodeRequired'];
		$result = $this->australian()->minPrecisionOf($floor)->validate(self::au());

		foreach ($all as $part => $constraint) {
			$this->assertSame(
				in_array($part, $required, true),
				$result->forConstraint($constraint)->passed(),
				"{$floor->value}: {$constraint}",
			);
		}
	}

	#[Test]
	public function an_address_requires_a_street_by_default(): void
	{
		$failed = $this->australian()->validate(self::au([], 'street'))->forConstraint('streetRequired');

		$this->assertTrue($failed->failed());
		$this->assertSame(Address\Part::Street, $failed->part);
	}

	#[Test]
	public function a_locality_floor_accepts_an_area_with_no_street(): void
	{
		$field = $this->australian()->minPrecisionOf(Precision::Locality);

		$this->assertFalse($field->validate(self::au([], 'street'))->anyFailed());
	}

	#[Test]
	public function a_shallow_floor_still_accepts_a_deep_value(): void
	{
		// Monotonicity, and the reason "an address or an area" is one field rather than a union.
		$field = $this->australian()->minPrecisionOf(Precision::Country);

		$this->assertFalse($field->validate(self::au())->anyFailed());
		$this->assertFalse($field->validate(self::au([], 'street', 'locality', 'subdivision', 'postal_code'))->anyFailed());
	}

	// ── attendability ──────────────────────────────────────────────────────────────────────

	#[Test]
	public function a_post_office_box_is_accepted_by_default(): void
	{
		// The narrower claim is refusing one, so the author makes it. A PO box is a perfectly
		// good billing address.
		$this->assertFalse($this->australian()->validate(self::au(['street' => ['PO Box 5']]))->anyFailed());
	}

	#[Test]
	public function a_post_office_box_is_refused_once_the_field_asks_for_somewhere_to_go(): void
	{
		$result = $this->australian()->mustBeVisitable()->validate(self::au(['street' => ['PO Box 5']]));

		$this->assertTrue($result->forConstraint('streetVisitable')->failed());
	}

	/** @return array<string, array{list<string>}> */
	public static function deliveryReceptacles(): array
	{
		return [
			'PO Box' => [['PO Box 5']],
			'P.O. Box' => [['P.O. Box 5']],
			'GPO Box' => [['GPO Box 5']],
			'post office box' => [['Post Office Box 5']],
			'locked bag' => [['Locked Bag 99']],
			'private bag' => [['Private Bag 7']],
			'roadside mail box' => [['RMB 12']],
			'on the second line' => [['Level 3', 'PO Box 5']],
			'inside one line' => [["Level 3\nPO Box 5"]],
		];
	}

	#[Test]
	#[DataProvider('deliveryReceptacles')]
	public function every_line_is_tested_for_a_delivery_receptacle(array $street): void
	{
		// A box written on the second line used to slip past: the pattern read line one only.
		$result = $this->australian()->mustBeVisitable()->validate(self::au(['street' => $street]));

		$this->assertTrue($result->forConstraint('streetVisitable')->failed());
	}

	#[Test]
	public function a_street_that_merely_looks_like_one_is_not_a_box(): void
	{
		$result = $this->australian()->mustBeVisitable()->validate(self::au(['street' => ['12 Rrunway Close']]));

		$this->assertTrue($result->forConstraint('streetVisitable')->passed());
	}

	#[Test]
	public function depth_and_attendability_are_independent(): void
	{
		// The combination a three-case enum could not express: no street required, but a street
		// that *is* given must name somewhere you can go.
		$field = $this->australian()
			->minPrecisionOf(Precision::Locality)
			->mustBeVisitable();

		$this->assertFalse($field->validate(self::au([], 'street'))->anyFailed());

		$withBox = $field->validate(self::au(['street' => ['PO Box 5']]));

		$this->assertTrue($withBox->forConstraint('streetVisitable')->failed());
		$this->assertTrue($withBox->forConstraint('streetRequired')->skipped());
	}

	#[Test]
	public function no_combination_of_the_two_dials_is_refused(): void
	{
		foreach (Precision::cases() as $floor) {
			$field = $this->australian()->minPrecisionOf($floor)->mustBeVisitable();

			$this->assertSame($floor, $field->precision);
			$this->assertTrue($field->streetVisitable);
		}
	}

	// ── how many lines ─────────────────────────────────────────────────────────────────────

	#[Test]
	public function a_street_may_run_to_three_lines(): void
	{
		$street = ['Level 3', 'Tower B', '1 Denham St'];

		$this->assertTrue($this->australian()->validate(self::au(['street' => $street]))->forConstraint('streetLineLimit')->passed());
	}

	#[Test]
	public function a_fourth_line_is_more_than_any_country_has(): void
	{
		$street = ['Level 3', 'Tower B', 'Suite 9', '1 Denham St'];
		$failed = $this->australian()->validate(self::au(['street' => $street]))->forConstraint('streetLineLimit');

		$this->assertTrue($failed->failed());
		$this->assertSame(3, $failed->bound);
	}

	#[Test]
	public function the_line_limit_is_answerable_with_no_country_allowed(): void
	{
		// Every one of the 206 countries uses exactly three, so this is the one country-driven
		// bound that stays declarable on a free-form field.
		$this->assertSame(3, $this->createField()->constraints->named('streetLineLimit')->bound);
	}

	// ── parts a country does not have ──────────────────────────────────────────────────────

	/**
	 * A part the submitted country's format has no place for is reported against *that part*.
	 *
	 * There used to be one `usedParts` constraint for all of these, and because which part
	 * offends varies per request it could not name one — so a form had no input to attach the
	 * error to, and a message pack got one sentence for every variant. Four constraints, one per
	 * part that can be unused, each carrying its own `part`. Street is absent from the list
	 * because all 206 countries use it.
	 *
	 * @return array<string, array{string, string, array<string, mixed>}>
	 */
	public static function partsACountryMayNotHave(): array
	{
		return [
			'Great Britain has no subdivision' => [
				'GB',
				'subdivisionUsed',
				['street' => ['10 Downing St'], 'locality' => 'London', 'subdivision' => 'Greater London', 'postal_code' => 'SW1A 2AA', 'country' => 'GB'],
			],
			// The Australian trap: a "suburb" here is the locality. Cardiff NSW 2285 has no
			// dependent locality, and Newcastle — the city it sits in — is not in the address.
			'Australia has no dependent locality' => [
				'AU',
				'dependentLocalityUsed',
				['street' => ['12 Macquarie Rd'], 'locality' => 'Cardiff', 'dependent_locality' => 'Newcastle', 'subdivision' => 'NSW', 'postal_code' => '2285', 'country' => 'AU'],
			],
			'the Emirates have no locality' => [
				'AE',
				'localityUsed',
				['street' => ['Sheikh Zayed Rd'], 'locality' => 'Dubai', 'subdivision' => 'AE-DU', 'country' => 'AE'],
			],
			'the Emirates have no postcode' => [
				'AE',
				'postalCodeUsed',
				['street' => ['Sheikh Zayed Rd'], 'subdivision' => 'AE-DU', 'postal_code' => '00000', 'country' => 'AE'],
			],
			'Panama has no postcode' => [
				'PA',
				'postalCodeUsed',
				['street' => ['Calle 50'], 'locality' => 'Ciudad de Panama', 'postal_code' => '00000', 'country' => 'PA'],
			],
		];
	}

	#[Test]
	#[DataProvider('partsACountryMayNotHave')]
	public function a_part_the_country_does_not_use_is_reported_against_that_part(
		string $country,
		string $constraint,
		array $address,
	): void {
		$failed = (new Address(new FieldName('billing'), [$country]))
			->validate((object) $address)
			->forConstraint($constraint);

		$this->assertTrue($failed->failed(), $constraint);

		// The whole point: a form knows which input to mark.
		$this->assertSame(
			['subdivisionUsed' => 'subdivision', 'dependentLocalityUsed' => 'dependent_locality', 'localityUsed' => 'locality', 'postalCodeUsed' => 'postal_code'][$constraint],
			$failed->part?->value,
		);
	}

	#[Test]
	public function two_parts_a_country_does_not_have_are_two_failures(): void
	{
		// One constraint could only ever report one of these, so the second was invisible until
		// the submitter fixed the first and tried again.
		$result = (new Address(new FieldName('billing'), ['GB']))->validate((object) [
			'street' => ['10 Downing St'],
			'locality' => 'London',
			'subdivision' => 'Greater London',
			'dependent_locality' => 'Whitehall',
			'postal_code' => 'SW1A 2AA',
			'country' => 'GB',
		]);

		$this->assertTrue($result->forConstraint('subdivisionUsed')->failed());
		$this->assertTrue($result->forConstraint('dependentLocalityUsed')->failed());
	}

	#[Test]
	public function a_part_the_country_does_use_passes(): void
	{
		$result = $this->australian()->validate(self::au());

		$this->assertTrue($result->forConstraint('subdivisionUsed')->passed());
		$this->assertTrue($result->forConstraint('localityUsed')->passed());
		$this->assertTrue($result->forConstraint('postalCodeUsed')->passed());
	}

	#[Test]
	public function a_part_nobody_submitted_is_not_asked_about(): void
	{
		// Skipped rather than passed: "you did not send a dependent locality" is not a verdict
		// on whether Australia has one.
		$result = $this->australian()->validate(self::au());

		$this->assertTrue($result->forConstraint('dependentLocalityUsed')->skipped());
	}

	#[Test]
	public function whether_a_country_uses_a_part_is_declarable_for_one_country(): void
	{
		$au = new Address(new FieldName('billing'), ['AU']);
		$free = $this->createField();

		$this->assertTrue($au->constraints->named('subdivisionUsed')->bound);
		$this->assertFalse($au->constraints->named('dependentLocalityUsed')->bound);
		$this->assertNull($free->constraints->named('subdivisionUsed')->bound);
	}

	#[Test]
	public function cardiff_nsw_2285_is_a_complete_australian_address(): void
	{
		$result = $this->australian()->validate((object) [
			'street' => ['12 Macquarie Rd'],
			'locality' => 'Cardiff',
			'subdivision' => 'NSW',
			'postal_code' => '2285',
			'country' => 'AU',
		]);

		$this->assertFalse($result->anyFailed());
	}

	// ── countries ──────────────────────────────────────────────────────────────────────────

	#[Test]
	public function it_allows_every_country_by_default(): void
	{
		$this->assertSame([], $this->createField()->allowedCountries);
	}

	#[Test]
	public function an_allowed_country_may_be_written_any_way_the_author_likes(): void
	{
		$field = $this->createField()->allowCountries('au', 'New Zealand', 'JPN');

		$this->assertSame(['AU', 'NZ', 'JP'], $field->allowedCountries);
	}

	#[Test]
	public function an_unknown_country_is_refused_where_it_is_written(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new Address(new FieldName('a'), ['ZZ']);
	}

	#[Test]
	public function it_reports_a_country_that_is_not_allowed(): void
	{
		$result = $this->australian()->validate((object) [
			'street' => ['1 Queen St'],
			'locality' => 'Auckland',
			'subdivision' => 'AUK',
			'postal_code' => '1010',
			'country' => 'NZ',
		]);

		$failed = $result->forConstraint('allowedCountries');

		$this->assertTrue($failed->failed());
		$this->assertSame(['AU'], $failed->bound);
	}

	#[Test]
	public function a_country_outside_the_allow_list_is_reported_once(): void
	{
		// Deriving the postcode rule from a country already reported would turn one mistake into
		// several failures.
		$result = $this->australian()->validate((object) [
			'street' => ['1 Queen St'],
			'locality' => 'Auckland',
			'subdivision' => 'AUK',
			'postal_code' => '1010',
			'country' => 'NZ',
		]);

		$this->assertTrue($result->forConstraint('postalCodeFormat')->skipped());
		$this->assertTrue($result->forConstraint('localityRequired')->skipped());
	}

	#[Test]
	public function it_skips_the_allow_list_when_every_country_is_allowed(): void
	{
		$this->assertTrue($this->createField()->validate(self::au())->forConstraint('allowedCountries')->skipped());
	}

	// ── postcodes ──────────────────────────────────────────────────────────────────────────

	#[Test]
	public function it_reports_a_postcode_that_is_wrong_for_its_country(): void
	{
		$failed = $this->australian()->validate(self::au(['postal_code' => '99']))->forConstraint('postalCodeFormat');

		$this->assertTrue($failed->failed());
		$this->assertSame(Address\Part::PostalCode, $failed->part);
		$this->assertSame('\d{4}', $failed->bound);
	}

	#[Test]
	public function a_free_form_field_still_checks_the_postcode_against_the_submitted_country(): void
	{
		// The submitter said which country, so checking their postcode against it is reading
		// what they wrote rather than guessing.
		$failed = $this->createField()->validate(self::au(['postal_code' => '99']))->forConstraint('postalCodeFormat');

		$this->assertTrue($failed->failed());
	}

	#[Test]
	public function a_postcode_failure_reports_the_pattern_that_applied(): void
	{
		$field = $this->createField()->allowCountries('AU', 'NZ');
		$failed = $field->validate(self::au(['postal_code' => '99']))->forConstraint('postalCodeFormat');

		// Not declarable up front with two countries allowed, but the one that applied is.
		$this->assertNull($field->constraints->named('postalCodeFormat')->bound);
		$this->assertSame('\d{4}', $failed->bound);
	}

	// ── subdivisions ───────────────────────────────────────────────────────────────────────

	#[Test]
	public function a_subdivision_is_reported_where_the_country_uses_one_without_requiring_it(): void
	{
		// Ireland: a wrong county is reportable, because nothing downstream depends on it there.
		// In Australia the same mistake makes the address unreadable instead — see ValueTest.
		$result = (new Address(new FieldName('billing'), ['IE']))->validate((object) [
			'street' => ['1 Main St'],
			'locality' => 'Carlow',
			'subdivision' => 'Banana',
			'country' => 'IE',
		]);

		$this->assertTrue($result->forConstraint('knownSubdivision')->failed());
	}

	/**
	 * A subdivision's own postcode pattern replaces its country's, rather than narrowing it.
	 *
	 * @return array<string, array{string, string, string, bool, string}>
	 */
	public static function subdivisionPostcodes(): array
	{
		return [
			// Taiwan's pattern admits 3 to 6 digits; China's admits exactly 6. So these were
			// *rejected* while being perfectly valid — a wrong answer a submitter sees.
			'a 3-digit Taiwan postcode' => ['CN', 'CN-TW', '100', true, '\d{3}(\d{2,3})?'],
			'a 5-digit Taiwan postcode' => ['CN', 'CN-TW', '10041', true, '\d{3}(\d{2,3})?'],
			'a 6-digit Taiwan postcode' => ['CN', 'CN-TW', '100412', true, '\d{3}(\d{2,3})?'],
			'a 2-digit Taiwan postcode' => ['CN', 'CN-TW', '10', false, '\d{3}(\d{2,3})?'],

			// Macau is a single code. Any other six digits passed against China's pattern.
			'the Macau postcode' => ['CN', 'CN-MO', '999078', true, '999078'],
			'a Shanghai postcode, in Macau' => ['CN', 'CN-MO', '200000', false, '999078'],

			// A subdivision with no override still answers to its country.
			'Shanghai, which overrides nothing' => ['CN', 'CN-SH', '200000', true, '\d{6}'],

			// Colombia overrides for every department.
			'a Bogota postcode' => ['CO', 'CO-DC', '110111', true, '11\d{4}'],
			'an Antioquia postcode, in Bogota' => ['CO', 'CO-DC', '059999', false, '11\d{4}'],
		];
	}

	#[Test]
	#[DataProvider('subdivisionPostcodes')]
	public function a_postcode_is_judged_against_the_subdivisions_own_pattern(
		string $country,
		string $subdivision,
		string $postcode,
		bool $valid,
		string $pattern,
	): void {
		$result = $this->createField()->validate((object) [
			'street' => ['1 Main St'],
			'locality' => 'Somewhere',
			'subdivision' => $subdivision,
			'postal_code' => $postcode,
			'country' => $country,
		]);

		$check = $result->forConstraint('postalCodeFormat');

		$this->assertSame($valid, $check->passed(), "{$subdivision} {$postcode}");

		// And the reported bound names the rule that actually applied, not the country's.
		$this->assertSame($pattern, $check->bound);
	}

	#[Test]
	public function a_postcode_falls_back_to_the_country_when_no_subdivision_is_given(): void
	{
		// China requires one, so this is unreadable rather than a constraint failure — but the
		// declared bound on a China-only field is still the country's pattern, because no
		// subdivision is known when the field is built.
		$field = new Address(new FieldName('billing'), ['CN']);

		$this->assertSame('\d{6}', $field->constraints->named('postalCodeFormat')->bound);
	}

	#[Test]
	public function a_known_subdivision_passes(): void
	{
		$this->assertTrue($this->australian()->validate(self::au())->forConstraint('knownSubdivision')->passed());
	}

	#[Test]
	public function the_subdivision_is_checked_as_iso_3166_2_writes_it(): void
	{
		$resolved = $this->australian()->resolve(self::au(['subdivision' => 'queensland']));

		$this->assertSame('AU-QLD', $resolved->value->subdivision);
	}

	// ── the value object is the field's ────────────────────────────────────────────────────

	#[Test]
	public function an_address_that_cannot_be_read_fails_the_shape_and_skips_everything(): void
	{
		$result = $this->australian()->validate((object) ['street' => ['1 Denham St'], 'country' => 'Banana']);

		$this->assertTrue($result->shape->wasUnreadable());

		// Readability is the precondition every constraint depends on, not one more rule among
		// them — so nothing is judged, rather than everything failing at once.
		foreach ($result->constraintNames as $name) {
			$this->assertTrue($result->forConstraint($name)->skipped(), "{$name} should have skipped");
		}
	}

	#[Test]
	public function it_has_no_default_value(): void
	{
		$this->assertNull($this->australian()->defaultValue);
	}

	#[Test]
	public function an_address_is_not_a_string(): void
	{
		$this->assertFalse($this->australian()->resolve(self::au())->value instanceof \Stringable);
	}

	#[Test]
	public function clearing_the_allow_list_accepts_every_country_again(): void
	{
		$field = $this->australian()->clearAllowedCountries();

		$this->assertSame([], $field->allowedCountries);
		$this->assertTrue($field->validate(self::au())->forConstraint('allowedCountries')->skipped());
	}

	#[Test]
	public function a_wither_leaves_the_field_it_was_called_on_alone(): void
	{
		$original = $this->australian();
		$narrowed = $original->mustBeVisitable()->minPrecisionOf(Precision::Locality);

		$this->assertFalse($original->streetVisitable);
		$this->assertSame(Precision::Street, $original->precision);
		$this->assertTrue($narrowed->streetVisitable);
		$this->assertSame(Precision::Locality, $narrowed->precision);
	}

	// ── one read path for a derived bound ──────────────────────────────────────────────────

	/** @return array<string, array{string}> */
	public static function derivedFacts(): array
	{
		return [
			'streetRequired' => ['streetRequired'],
			'localityRequired' => ['localityRequired'],
			'subdivisionRequired' => ['subdivisionRequired'],
			'postalCodeRequired' => ['postalCodeRequired'],
			'postalCodeFormat' => ['postalCodeFormat'],
			'streetLineLimit' => ['streetLineLimit'],
			'localityUsed' => ['localityUsed'],
			'subdivisionUsed' => ['subdivisionUsed'],
		];
	}

	#[Test]
	#[DataProvider('derivedFacts')]
	public function a_derived_fact_is_not_also_a_property(string $name): void
	{
		// One read path: `requirementsFor()`. A fact with two accessors is a fact that can
		// disagree with itself, and every one of these is unanswerable while more than one
		// country is allowed.
		$this->assertFalse(property_exists(Address::class, $name), "Address::\${$name} should not exist");
	}

	#[Test]
	public function configuration_the_author_set_is_a_property(): void
	{
		$field = $this->australian();

		$this->assertSame(['AU'], $field->allowedCountries);
		$this->assertSame(Precision::Street, $field->precision);
		$this->assertFalse($field->streetVisitable);
	}
}
