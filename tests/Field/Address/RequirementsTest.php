<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Address;

use Meraki\Schema\Exception\InvalidConfiguration;
use Meraki\Schema\Exception\UnreadableAddressFormat;
use Meraki\Schema\Field\Address;
use Meraki\Schema\FieldName;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * What a port asks before anyone has submitted anything.
 *
 * Every country-driven bound is unanswerable while more than one country is allowed — and
 * free-form is the default — so without this a port cannot mark an input required until after a
 * submission, and a country dropdown changes the answer as the user types. One method answers
 * them all, keyed by whatever spelling the caller happens to hold.
 */
#[Group('field')]
#[CoversClass(Requirements::class)]
#[CoversClass(Address::class)]
final class RequirementsTest extends TestCase
{
	private function freeForm(): Address
	{
		return new Address(new FieldName('billing'));
	}

	/** @param list<string> $countries */
	private function restrictedTo(array $countries): Address
	{
		return new Address(new FieldName('billing'), $countries);
	}

	// ── what each country asks for ─────────────────────────────────────────────────────────

	/** @return array<string, array{string, list<string>}> */
	public static function requiredPartsByCountry(): array
	{
		return [
			'AU requires all four' => ['AU', ['street', 'locality', 'subdivision', 'postal_code']],
			'JP addresses by prefecture, not locality' => ['JP', ['street', 'subdivision', 'postal_code']],
			'AE has neither locality nor postcode' => ['AE', ['street', 'subdivision']],
			'PA has no postcode at all' => ['PA', ['street', 'locality']],
			'GB has no subdivision' => ['GB', ['street', 'locality', 'postal_code']],
		];
	}

	#[Test]
	#[DataProvider('requiredPartsByCountry')]
	public function it_reads_requiredness_off_the_country(string $country, array $expected): void
	{
		$requirements = $this->freeForm()->requirementsFor($country)[$country];

		$this->assertSame($expected, $requirements->requiredParts);
	}

	#[Test]
	public function the_country_itself_is_not_listed_as_a_required_part(): void
	{
		// It is required of every address everywhere, and the value refuses one without it, so
		// it is a fact about the shape rather than something a country asks for.
		$this->assertNotContains('country', $this->freeForm()->requirementsFor('AU')['AU']->requiredParts);
	}

	// ── the precision floor ────────────────────────────────────────────────────────────────

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
	public function the_floor_filters_what_the_country_asks_for(Precision $floor, array $expected): void
	{
		$field = $this->freeForm()->minPrecisionOf($floor);

		$this->assertSame($expected, $field->requirementsFor('AU')['AU']->requiredParts);
	}

	#[Test]
	public function the_floor_never_invents_a_requirement(): void
	{
		// Panama has no postcode, so no floor can make one required.
		foreach (Precision::cases() as $floor) {
			$requirements = $this->freeForm()->minPrecisionOf($floor)->requirementsFor('PA')['PA'];

			$this->assertNotContains('postal_code', $requirements->requiredParts);
		}
	}

	#[Test]
	public function the_floor_does_not_touch_what_the_country_uses(): void
	{
		// A part being below the floor means "not required here", not "not part of an address".
		$deep = $this->freeForm()->minPrecisionOf(Precision::Street);
		$shallow = $this->freeForm()->minPrecisionOf(Precision::Country);

		$this->assertSame(
			$deep->requirementsFor('AU')['AU']->usedParts,
			$shallow->requirementsFor('AU')['AU']->usedParts,
		);
	}

	// ── what each country uses ─────────────────────────────────────────────────────────────

	#[Test]
	public function it_reads_the_used_parts_off_the_country(): void
	{
		$this->assertSame(
			['street', 'locality', 'subdivision', 'postal_code'],
			$this->freeForm()->requirementsFor('AU')['AU']->usedParts,
		);
	}

	#[Test]
	public function a_country_with_no_subdivision_does_not_list_one(): void
	{
		$this->assertNotContains('subdivision', $this->freeForm()->requirementsFor('GB')['GB']->usedParts);
	}

	#[Test]
	public function a_country_with_no_postcode_does_not_list_one(): void
	{
		$this->assertNotContains('postal_code', $this->freeForm()->requirementsFor('PA')['PA']->usedParts);
	}

	#[Test]
	public function ireland_uses_a_dependent_locality_and_australia_does_not(): void
	{
		// The Australian trap: a "suburb" is the locality, so Cardiff NSW 2285 has no dependent
		// locality at all. Newcastle, the city it sits in, is not part of the address.
		$this->assertContains('dependent_locality', $this->freeForm()->requirementsFor('IE')['IE']->usedParts);
		$this->assertNotContains('dependent_locality', $this->freeForm()->requirementsFor('AU')['AU']->usedParts);
	}

	// ── the rest of the answer ─────────────────────────────────────────────────────────────

	#[Test]
	public function it_carries_the_countrys_postcode_pattern(): void
	{
		$this->assertSame('\d{4}', $this->freeForm()->requirementsFor('AU')['AU']->postalCodeFormat);
		$this->assertNull($this->freeForm()->requirementsFor('PA')['PA']->postalCodeFormat);
	}

	#[Test]
	public function it_carries_the_subdivision_codes_as_iso_3166_2_writes_them(): void
	{
		$this->assertSame(
			['AU-ACT', 'AU-NSW', 'AU-NT', 'AU-QLD', 'AU-SA', 'AU-TAS', 'AU-VIC', 'AU-WA'],
			array_keys($this->freeForm()->requirementsFor('AU')['AU']->subdivisions),
		);
	}

	#[Test]
	public function it_carries_the_name_each_code_stands_for(): void
	{
		// The name is the matching key for submitted input as much as it is a label — an address
		// may name its state in full — so the mapping to the canonical code has to be published
		// rather than left for a port to rebuild against the same data.
		$subdivisions = $this->freeForm()->requirementsFor('AU')['AU']->subdivisions;

		$this->assertSame('Queensland', $subdivisions['AU-QLD']);
		$this->assertSame('New South Wales', $subdivisions['AU-NSW']);
	}

	#[Test]
	public function a_country_with_no_subdivisions_carries_an_empty_list(): void
	{
		$this->assertSame([], $this->freeForm()->requirementsFor('GB')['GB']->subdivisions);
	}

	// ── postcode patterns, and the subdivisions that override them ─────────────────────────

	#[Test]
	public function most_countries_override_no_postcode_pattern(): void
	{
		foreach (['AU', 'GB', 'JP', 'DE'] as $country) {
			$rules = $this->freeForm()->requirementsFor($country)[$country];

			$this->assertSame([], $rules->postalCodeFormatOverrides, $country);
		}
	}

	#[Test]
	public function a_subdivision_may_carry_its_own_postcode_pattern(): void
	{
		// 36 of 1548 do, across exactly two countries. Macau and Hong Kong are single codes
		// rather than patterns, and Taiwan's is *wider* than China's — which is why an override
		// replaces the country's rather than narrowing it.
		$cn = $this->freeForm()->requirementsFor('CN')['CN'];

		$this->assertSame('\d{6}', $cn->postalCodeFormat);
		$this->assertSame('999078', $cn->postalCodeFormatOverrides['CN-MO']);
		$this->assertSame('999077', $cn->postalCodeFormatOverrides['CN-HK']);
		$this->assertSame('\d{3}(\d{2,3})?', $cn->postalCodeFormatOverrides['CN-TW']);
	}

	#[Test]
	public function colombia_overrides_a_pattern_for_every_department(): void
	{
		$co = $this->freeForm()->requirementsFor('CO')['CO'];

		$this->assertSame('11\d{4}', $co->postalCodeFormatOverrides['CO-DC']);
		$this->assertCount(33, $co->postalCodeFormatOverrides);
	}

	/** @return array<string, array{?string, string}> */
	public static function spellingsOfASubdivision(): array
	{
		return [
			'nothing chosen yet' => [null, '\d{6}'],
			'full iso code' => ['CN-MO', '999078'],
			'full iso code, lowercased' => ['cn-mo', '999078'],
			'bare code' => ['MO', '999078'],
			'bare code, lowercased' => ['mo', '999078'],
			'by name' => ['Macau', '999078'],
			'by name, lowercased' => ['macau', '999078'],
			'a subdivision with no override' => ['CN-SH', '\d{6}'],
			'one this country does not have' => ['AU-QLD', '\d{6}'],
		];
	}

	#[Test]
	#[DataProvider('spellingsOfASubdivision')]
	public function the_pattern_that_applies_is_asked_for_once(?string $subdivision, string $expected): void
	{
		$cn = $this->freeForm()->requirementsFor('CN')['CN'];

		$this->assertSame($expected, $cn->postalCodeFormatFor($subdivision));
	}

	#[Test]
	public function a_country_with_no_postcode_has_none_to_apply(): void
	{
		$pa = $this->freeForm()->requirementsFor('PA')['PA'];

		$this->assertNull($pa->postalCodeFormatFor());
		$this->assertNull($pa->postalCodeFormatFor('PA-1'));
	}

	#[Test]
	public function a_country_with_no_subdivisions_still_has_a_pattern(): void
	{
		// 133 of 206 countries are in this position: a postcode pattern and nothing below the
		// country to vary it. A per-subdivision map alone would have nowhere to put theirs.
		$de = $this->freeForm()->requirementsFor('DE')['DE'];

		$this->assertSame('\d{5}', $de->postalCodeFormat);
		$this->assertSame('\d{5}', $de->postalCodeFormatFor());
	}

	#[Test]
	public function it_canonicalises_a_subdivision_spelling_on_its_own(): void
	{
		$au = $this->freeForm()->requirementsFor('AU')['AU'];

		$this->assertSame('AU-QLD', $au->subdivisionCodeFor('queensland'));
		$this->assertSame('AU-QLD', $au->subdivisionCodeFor('QLD'));
		$this->assertSame('AU-QLD', $au->subdivisionCodeFor('au-qld'));
		$this->assertNull($au->subdivisionCodeFor('Banana'));
	}

	#[Test]
	public function everything_a_consumer_needs_survives_json(): void
	{
		// The rule has to be applicable from the document alone — `overrides[subdivision] ??
		// postalCodeFormat` is one line in any language — so none of it may hide behind a method.
		$cn = $this->freeForm()->requirementsFor('CN')['CN'];

		$encoded = json_decode((string) json_encode($cn), true);

		$this->assertSame('\d{6}', $encoded['postalCodeFormat']);
		$this->assertSame('999078', $encoded['postalCodeFormatOverrides']['CN-MO']);
		$this->assertSame('Macau', $encoded['subdivisions']['CN-MO']);
		$this->assertSame(['street', 'locality', 'subdivision', 'postal_code'], $encoded['requiredParts']);
		$this->assertSame(3, $encoded['streetLineLimit']);
	}

	#[Test]
	public function every_country_allows_three_address_lines(): void
	{
		foreach (['AU', 'JP', 'GB', 'PA', 'AE'] as $country) {
			$this->assertSame(3, $this->freeForm()->requirementsFor($country)[$country]->streetLineLimit);
		}
	}

	// ── how it is asked ────────────────────────────────────────────────────────────────────

	/** @return array<string, array{string}> */
	public static function spellingsOfAustralia(): array
	{
		return [
			'alpha-2' => ['AU'],
			'alpha-2 lowercase' => ['au'],
			'alpha-3' => ['AUS'],
			'alpha-3 lowercase' => ['aus'],
			'name' => ['Australia'],
			'name lowercase' => ['australia'],
		];
	}

	#[Test]
	#[DataProvider('spellingsOfAustralia')]
	public function it_takes_any_spelling_the_value_would_take(string $spelling): void
	{
		$requirements = $this->freeForm()->requirementsFor($spelling);

		$this->assertArrayHasKey($spelling, $requirements);
		$this->assertSame('AU', $requirements[$spelling]->country);
	}

	#[Test]
	public function it_keys_the_answer_by_the_spelling_that_was_asked(): void
	{
		// So a port holding alpha-3 gets its own vocabulary back, and the canonicalisation
		// table it would otherwise have to build for itself.
		$requirements = $this->freeForm()->requirementsFor('AUS', 'new zealand');

		$this->assertSame(['AUS', 'new zealand'], array_keys($requirements));
		$this->assertSame('AU', $requirements['AUS']->country);
		$this->assertSame('NZ', $requirements['new zealand']->country);
	}

	#[Test]
	public function with_no_arguments_it_answers_for_every_country_the_field_allows(): void
	{
		$field = $this->restrictedTo(['AU', 'NZ']);

		$this->assertSame(['AU', 'NZ'], array_keys($field->requirementsFor()));
	}

	#[Test]
	public function with_no_arguments_a_single_country_field_need_not_repeat_itself(): void
	{
		$this->assertSame(['AU'], array_keys($this->restrictedTo(['AU'])->requirementsFor()));
	}

	#[Test]
	public function with_no_arguments_a_free_form_field_refuses_to_answer(): void
	{
		// "Every country" is 206 answers, each with a subdivision list, and is almost certainly
		// not the question that was meant.
		$this->expectException(InvalidConfiguration::class);

		$this->freeForm()->requirementsFor();
	}

	#[Test]
	public function it_refuses_a_country_that_is_not_a_country(): void
	{
		$this->expectException(InvalidConfiguration::class);

		$this->freeForm()->requirementsFor('Banana');
	}

	#[Test]
	public function it_refuses_a_country_the_field_does_not_allow(): void
	{
		// Asking what a US address needs, of a field that only accepts Australian ones, is a
		// bug in the caller rather than a question with an answer.
		$this->expectException(InvalidConfiguration::class);

		$this->restrictedTo(['AU'])->requirementsFor('US');
	}

	#[Test]
	public function one_bad_country_refuses_the_whole_call(): void
	{
		// Rather than a partial map, which invites a silent gap where a lookup quietly missed.
		try {
			$this->freeForm()->requirementsFor('AU', 'Banana', 'NZ');
			$this->fail('expected the call to be refused');
		} catch (InvalidConfiguration $e) {
			$this->assertStringContainsString('Banana', $e->getMessage());
		}
	}

	#[Test]
	public function a_free_form_field_answers_for_any_country_it_is_given(): void
	{
		$this->assertSame('JP', $this->freeForm()->requirementsFor('JP')['JP']->country);
	}

	/** @return array<string, array{list<string>}> */
	public static function theSameCountryTwice(): array
	{
		return [
			'the same spelling' => [['AU', 'AU']],
			'two codes' => [['au', 'AUS']],
			'a code and a name' => [['AU', 'Australia']],
			'buried among others' => [['NZ', 'AUS', 'JP', 'australia']],
		];
	}

	#[Test]
	#[DataProvider('theSameCountryTwice')]
	public function it_refuses_to_answer_about_one_country_twice(array $countries): void
	{
		// Asking twice is a caller bug either way, and both outcomes hide it. The same spelling
		// collapses to one entry, so the result is quietly shorter than the question. Two
		// spellings of one country give two keys holding identical answers, so a caller looping
		// over them does the same work twice and cannot tell.
		$this->expectException(InvalidConfiguration::class);

		$this->freeForm()->requirementsFor(...$countries);
	}

	#[Test]
	public function the_refusal_names_the_country_and_both_spellings(): void
	{
		try {
			$this->freeForm()->requirementsFor('au', 'AUS');
			$this->fail('expected the call to be refused');
		} catch (InvalidConfiguration $e) {
			$this->assertStringContainsString('AU', $e->getMessage());
			$this->assertStringContainsString('au', $e->getMessage());
			$this->assertStringContainsString('AUS', $e->getMessage());
		}
	}

	#[Test]
	public function asking_about_different_countries_is_not_a_duplicate(): void
	{
		$requirements = $this->freeForm()->requirementsFor('AUS', 'NZL', 'japan');

		$this->assertSame(['AUS', 'NZL', 'japan'], array_keys($requirements));
	}

	#[Test]
	public function no_arguments_cannot_produce_a_duplicate(): void
	{
		// The allow-list is canonicalised and de-duplicated when it is built, so the no-argument
		// form has nothing to collide with — `allowCountries('AU', 'aus')` is one country.
		$field = $this->freeForm()->allowCountries('AU', 'aus', 'Australia', 'NZ');

		$this->assertSame(['AU', 'NZ'], array_keys($field->requirementsFor()));
	}

	#[Test]
	public function two_floors_asking_about_one_country_get_different_answers(): void
	{
		// A guard on the memoisation: the answer depends on the floor as well as the country,
		// so a cache keyed on the country alone would hand one field the other's requiredness.
		// Cheap to get wrong and silent when you do — the country is the obvious key.
		$street = $this->freeForm()->minPrecisionOf(Precision::Street);
		$locality = $this->freeForm()->minPrecisionOf(Precision::Locality);

		$this->assertSame(['street', 'locality', 'subdivision', 'postal_code'], $street->requirementsFor('AU')['AU']->requiredParts);
		$this->assertSame(['locality', 'subdivision', 'postal_code'], $locality->requirementsFor('AU')['AU']->requiredParts);

		// And again in the other order, in case the first answer is the one that sticks.
		$this->assertSame(['street', 'locality', 'subdivision', 'postal_code'], $street->requirementsFor('AU')['AU']->requiredParts);
	}

	/**
	 * What this method publishes, an address reads back as one of the country's.
	 *
	 * The contract a port relies on: `subdivisions` is what it renders as options, and the
	 * option the user picks comes straight back. Nothing else guarantees the two agree, and
	 * they did not — five countries key their subdivisions by name, so `HK-Kowloon` was
	 * published and then refused, making Hong Kong unusable through the documented path.
	 *
	 * Every subdivision of every country, because the failures clustered exactly where the
	 * hand-picked examples were not: AU and IE are both uppercase-coded and string-keyed.
	 */
	#[Test]
	public function every_subdivision_it_publishes_is_one_a_value_accepts(): void
	{
		$field = $this->freeForm();
		$rejected = [];
		$checked = 0;

		foreach (array_keys((new AddressFormatRepository())->getAll()) as $country) {
			$requirements = $field->requirementsFor($country)[$country];

			foreach (array_keys($requirements->subdivisions) as $code) {
				++$checked;

				try {
					$address = new Input((object) [
						'street' => ['1 Main St'],
						'locality' => 'Somewhere',
						'subdivision' => $code,
						'country' => $country,
					]);

					if ($address->subdivision !== $code) {
						$rejected[] = "{$code} became {$address->subdivision}";
					}

					if (in_array(Check::KnownSubdivision, array_column($address->violations, 'code'), true)) {
						$rejected[] = "{$code} is not one of {$country}'s";
					}
				} catch (\Throwable $e) {
					$rejected[] = "{$code}: " . $e->getMessage();
				}
			}
		}

		$this->assertGreaterThan(1500, $checked, 'expected the whole subdivision dataset');
		$this->assertSame([], $rejected, 'every published subdivision code must round-trip');
	}

	/**
	 * The addressing library still describes its fields as strings.
	 *
	 * Its own docblocks say `AddressField` objects. Everything here reads whichever shape
	 * arrives, so a change would not break us — but it is worth knowing when it happens, because
	 * the direction of that failure used to be silent: comparing strictly against strings and
	 * being handed objects emptied `requiredParts`, and an address was never incomplete again.
	 *
	 * `composer.json` allows `^2.0`, so this is reachable by an update inside the permitted
	 * range. If this test goes red, nothing is broken — read {@see Requirements::nameEach()} and
	 * move this assertion to the new shape.
	 */
	#[Test]
	public function the_addressing_library_still_describes_its_fields_as_strings(): void
	{
		$format = (new AddressFormatRepository())->get('AU');

		$this->assertContainsOnly('string', $format->getRequiredFields());
		$this->assertContainsOnly('string', $format->getUsedFields());
		$this->assertContains('addressLine1', $format->getRequiredFields());
	}

	/**
	 * And whichever shape it uses, the names come back the same.
	 *
	 * The three shapes `nameEach()` accepts, exercised directly because no version of the
	 * upstream library produces more than one of them at a time — which is exactly why the
	 * defensive read needs its own test rather than riding on the data.
	 */
	#[Test]
	#[DataProvider('fieldShapes')]
	public function a_field_name_is_read_whatever_shape_it_arrives_in(array $fields, array $expected): void
	{
		$nameEach = new \ReflectionMethod(Requirements::class, 'nameEach');

		$this->assertSame($expected, $nameEach->invoke(null, $fields));
	}

	/** @return iterable<string, array{array<int, mixed>, list<string>}> */
	public static function fieldShapes(): iterable
	{
		yield 'strings, as today' => [['addressLine1', 'locality'], ['addressLine1', 'locality']];

		yield 'a backed enum, as the docblocks claim' => [
			[AddressFieldShape::AddressLine1, AddressFieldShape::Locality],
			['addressLine1', 'locality'],
		];

		yield 'a Stringable' => [
			[new class() implements \Stringable {
				public function __toString(): string
				{
					return 'postalCode';
				}
			}],
			['postalCode'],
		];
	}

	#[Test]
	public function a_field_it_cannot_read_raises_rather_than_contributing_nothing(): void
	{
		// The whole point: an unreadable field must not quietly drop out of requiredParts.
		$this->expectException(UnreadableAddressFormat::class);

		(new \ReflectionMethod(Requirements::class, 'nameEach'))->invoke(null, [123]);
	}
}

/** Stands in for the shape the upstream docblocks describe. @see RequirementsTest::fieldShapes() */
enum AddressFieldShape: string
{
	case AddressLine1 = 'addressLine1';
	case Locality = 'locality';
}
