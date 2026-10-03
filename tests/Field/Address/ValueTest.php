<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Address;

use Meraki\Schema\Exception\BrokenInputContract;
use Meraki\Schema\Field\MalformedValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The record an address is, and the things it refuses to be built out of.
 *
 * Refusing is not the same as failing a constraint. A constraint judges an address that could
 * be read; these are the cases where there is nothing to judge — a part that was sent but holds
 * nothing, a country that names no country, a subdivision that a country requires and does not
 * have. Everything downstream of the country is selected *by* the country, so without a usable
 * one nothing else is decidable.
 */
#[Group('field')]
#[CoversClass(Value::class)]
final class ValueTest extends TestCase
{
	/** @param array<string, mixed> $overrides */
	private static function address(array $overrides = [], string ...$without): Value
	{
		$parts = array_diff_key([
			'street' => ['7 Cunningham St'],
			'locality' => 'Emerald',
			'subdivision' => 'QLD',
			'postal_code' => '4720',
			'country' => 'AU',
		], array_flip($without));

		return new Value((object) array_merge($parts, $overrides));
	}

	// ── the parts ──────────────────────────────────────────────────────────────────────────

	#[Test]
	public function it_has_six_parts_and_names_them_as_submitted_data_does(): void
	{
		$this->assertSame(
			['street', 'dependent_locality', 'locality', 'subdivision', 'postal_code', 'country'],
			array_column(Part::cases(), 'value'),
		);
	}

	#[Test]
	public function the_addressee_is_not_part_of_the_address(): void
	{
		// organization went the way givenName and familyName already had: an address
		// identifies a place, not who is at it.
		$this->assertNotContains('organization', array_column(Part::cases(), 'value'));
	}

	#[Test]
	public function the_address_lines_are_one_part_rather_than_two(): void
	{
		$this->assertNotContains('line1', array_column(Part::cases(), 'value'));
		$this->assertNotContains('line2', array_column(Part::cases(), 'value'));
	}

	#[Test]
	public function iso_3166_2_calls_it_a_subdivision(): void
	{
		$this->assertNotContains('administrative_area', array_column(Part::cases(), 'value'));
	}

	#[Test]
	public function the_part_enum_and_parts_and_toArray_agree(): void
	{
		$address = self::address();

		$this->assertSame(array_column(Part::cases(), 'value'), array_keys($address->parts()));
		$this->assertSame(array_column(Part::cases(), 'value'), array_keys($address->toArray()));
	}

	// ── street is a list ───────────────────────────────────────────────────────────────────

	#[Test]
	public function street_holds_the_lines_as_a_list(): void
	{
		$address = self::address(['street' => ['Level 3', '7 Cunningham St']]);

		$this->assertSame(['Level 3', '7 Cunningham St'], $address->street);
	}

	#[Test]
	public function an_absent_street_is_an_empty_list(): void
	{
		$this->assertSame([], self::address([], 'street')->street);
	}

	#[Test]
	public function a_street_that_is_not_a_list_cannot_be_read(): void
	{
		$this->expectException(MalformedValue::class);

		self::address(['street' => "Level 3\n7 Cunningham St"]);
	}

	#[Test]
	public function a_street_line_that_is_not_a_string_cannot_be_read(): void
	{
		$this->expectException(MalformedValue::class);

		self::address(['street' => ['Level 3', 42]]);
	}

	#[Test]
	public function street_lines_are_kept_exactly_as_submitted(): void
	{
		// No trimming, no collapsing, no line-ending repair: there is no standard normal form
		// for an address line to normalise towards, so tidying is the port's decision.
		$address = self::address(['street' => ['  Level 3  ', '7  Cunningham   St']]);

		$this->assertSame(['  Level 3  ', '7  Cunningham   St'], $address->street);
	}

	// ── present but empty is unreadable ────────────────────────────────────────────────────

	/** @return array<string, array{string}> */
	public static function everyStringPart(): array
	{
		return [
			'locality' => ['locality'],
			'subdivision' => ['subdivision'],
			'postal_code' => ['postal_code'],
			'country' => ['country'],
			'dependent_locality' => ['dependent_locality'],
		];
	}

	/** @return array<string, array{string}> */
	public static function emptySpellings(): array
	{
		return [
			'empty string' => [''],
			'spaces' => ['   '],
			'newlines' => ["\n\n"],
			'tab' => ["\t"],
		];
	}

	#[Test]
	#[DataProvider('everyStringPart')]
	public function a_part_sent_as_an_empty_string_cannot_be_read(string $part): void
	{
		$this->expectException(MalformedValue::class);

		self::address([$part => '']);
	}

	#[Test]
	#[DataProvider('everyStringPart')]
	public function a_part_sent_as_whitespace_cannot_be_read(string $part): void
	{
		$this->expectException(MalformedValue::class);

		self::address([$part => '   ']);
	}

	#[Test]
	#[DataProvider('emptySpellings')]
	public function a_street_line_with_no_content_cannot_be_read(string $blank): void
	{
		$this->expectException(MalformedValue::class);

		self::address(['street' => [$blank]]);
	}

	#[Test]
	public function a_street_sent_as_an_empty_list_cannot_be_read(): void
	{
		// Sent-and-holding-nothing is not the same as not sent. Omit the part instead.
		$this->expectException(MalformedValue::class);

		self::address(['street' => []]);
	}

	#[Test]
	public function the_refusal_names_the_part_it_could_not_read(): void
	{
		try {
			self::address(['locality' => '   ']);
			$this->fail('expected the address to be unreadable');
		} catch (MalformedValue $e) {
			$this->assertStringContainsString('locality', $e->getMessage());
		}
	}

	#[Test]
	public function an_omitted_part_is_absent_rather_than_unreadable(): void
	{
		$address = self::address([], 'dependent_locality', 'postal_code');

		$this->assertNull($address->dependentLocality);
		$this->assertNull($address->postalCode);
	}

	#[Test]
	public function a_key_that_is_not_a_part_cannot_be_read(): void
	{
		// The renames make this earn its keep: anything still sending `line1` would otherwise
		// build an address with no street at all and be told 'street is required', which names
		// the symptom rather than the stale key that caused it.
		//
		// BrokenInputContract rather than MalformedValue, and the difference is who is wrong. A
		// MalformedValue is absorbed and reported, because it came from a submitter. `line1` is
		// a key, and keys are not submitter data in any protocol — so it escapes.
		$this->expectException(BrokenInputContract::class);

		self::address(['line1' => '7 Cunningham St']);
	}

	#[Test]
	public function the_refusal_names_the_key_it_did_not_recognise(): void
	{
		try {
			self::address(['organization' => 'Meraki']);
			$this->fail('expected the address to be unreadable');
		} catch (BrokenInputContract $e) {
			$this->assertStringContainsString('organization', $e->getMessage());
			$this->assertSame(['organization'], $e->unknownKeys);
		}
	}

	#[Test]
	public function a_part_that_is_not_empty_keeps_its_whitespace(): void
	{
		// ' 4720 ' has content, so it is readable — and reported as submitted, so the
		// submitter sees what they sent rather than a silent repair.
		$this->assertSame(' 4720 ', self::address(['postal_code' => ' 4720 '])->postalCode);
	}

	// ── the country ────────────────────────────────────────────────────────────────────────

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
	public function it_takes_any_iso_3166_1_spelling_and_stores_the_alpha_2_code(string $spelling): void
	{
		$this->assertSame('AU', self::address(['country' => $spelling])->countryCode);
	}

	#[Test]
	#[DataProvider('spellingsOfAustralia')]
	public function codeFor_resolves_the_same_spellings(string $spelling): void
	{
		$this->assertSame('AU', Value::codeFor($spelling));
	}

	#[Test]
	public function a_country_it_cannot_recognise_cannot_be_read(): void
	{
		// Everything else is selected by the country — the postcode pattern, the subdivision
		// set, the required parts — so an unrecognised one leaves nothing decidable. Keeping
		// it and skipping every check is how an alpha-3 code used to pass unvalidated.
		$this->expectException(MalformedValue::class);

		self::address(['country' => 'Banana']);
	}

	#[Test]
	public function a_numeric_country_code_cannot_be_read(): void
	{
		// ISO 3166-1 numeric-3 is deliberately not accepted: it is rare in addresses and its
		// leading zeros do not survive a JSON producer that sends it as a number.
		$this->expectException(MalformedValue::class);

		self::address(['country' => '036']);
	}

	#[Test]
	public function an_address_with_no_country_keeps_its_other_parts(): void
	{
		// Refused until this release, on the reasoning that a country gives the rest of an
		// address its meaning. It does — and so does the currency on `Money`, which reports
		// `currencyRequired` and skips what it cannot judge. The field names the part now.
		$address = self::address([], 'country');

		$this->assertNull($address->countryCode);
		$this->assertSame('Emerald', $address->locality);

		// A country that *was* given and is not one is still unreadable.
		$this->expectException(MalformedValue::class);

		self::address(['country' => 'Zorbia']);
	}

	// ── the subdivision ────────────────────────────────────────────────────────────────────

	/** @return array<string, array{string}> */
	public static function spellingsOfQueensland(): array
	{
		return [
			'code' => ['QLD'],
			'code lowercase' => ['qld'],
			'iso 3166-2' => ['AU-QLD'],
			'iso 3166-2 lowercase' => ['au-qld'],
			'name' => ['Queensland'],
			'name lowercase' => ['queensland'],
		];
	}

	#[Test]
	#[DataProvider('spellingsOfQueensland')]
	public function it_stores_the_full_iso_3166_2_code_whatever_spelling_arrives(string $spelling): void
	{
		$this->assertSame('AU-QLD', self::address(['subdivision' => $spelling])->subdivision);
	}

	/** @return array<string, array{string, string, string}> */
	public static function subdivisionsByName(): array
	{
		// Every one of these countries keys its subdivisions with a numeric string, which PHP
		// turns into an `int` the moment it becomes an array key. Resolving by name returned
		// that key, and an `int` from a `?string` method is a TypeError — a fatal that escapes
		// validate() entirely rather than arriving as a MalformedValue a caller can catch.
		return [
			'Japan, prefecture 13' => ['JP', 'Tokyo', 'JP-13'],
			'Japan, prefecture 01' => ['JP', 'Hokkaido', 'JP-01'],
			'Korea' => ['KR', 'Seoul', 'KR-11'],
			'Thailand' => ['TH', 'Bangkok', 'TH-10'],
			'Ukraine' => ['UA', 'Kyiv', 'UA-30'],
		];
	}

	#[Test]
	#[DataProvider('subdivisionsByName')]
	public function a_subdivision_named_in_a_numerically_keyed_country_resolves(
		string $country,
		string $name,
		string $expected,
	): void {
		$address = new Value((object) [
			'street' => ['1 Main St'],
			'locality' => 'Somewhere',
			'subdivision' => $name,
			'postal_code' => $country === 'JP' ? '100-0001' : null,
			'country' => $country,
		]);

		$this->assertSame($expected, $address->subdivision);
	}

	/** @return array<string, array{string, string, string}> */
	public static function subdivisionsKeyedByTheirOwnName(): array
	{
		// Five countries store a name where the rest store a code, so the ISO 3166-2 form this
		// library publishes for them — `HK-Kowloon` — is a name behind a prefix. Uppercasing the
		// candidate before the lookup meant the library refused the exact value it had handed a
		// port to render, which made Hong Kong, Cape Verde and the Caymans unusable outright.
		return [
			'Hong Kong' => ['HK', 'HK-Kowloon', 'HK-Kowloon'],
			'Hong Kong, lowercased' => ['HK', 'hk-kowloon', 'HK-Kowloon'],
			'Hong Kong, bare name' => ['HK', 'Kowloon', 'HK-Kowloon'],
			'Cape Verde' => ['CV', 'CV-Boa Vista', 'CV-Boa Vista'],
			'Cayman Islands' => ['KY', 'KY-Grand Cayman', 'KY-Grand Cayman'],
		];
	}

	#[Test]
	#[DataProvider('subdivisionsKeyedByTheirOwnName')]
	public function a_subdivision_this_library_published_is_accepted_back(
		string $country,
		string $submitted,
		string $expected,
	): void {
		$address = new Value((object) [
			'street' => ['1 Main St'],
			'locality' => 'Somewhere',
			'subdivision' => $submitted,
			'country' => $country,
		]);

		$this->assertSame($expected, $address->subdivision);
	}

	#[Test]
	public function an_absent_street_survives_a_round_trip_through_toArray(): void
	{
		// `toArray()` is the serialisation seam, and a submitted empty list is refused — so
		// emitting `[]` for an absent street made a value this library produced unreadable to
		// itself. Anything that persists and reloads an address, schema-json included, goes
		// through here.
		$area = Value::of(locality: 'Emerald', subdivision: 'QLD', postalCode: '4720', country: 'AU');

		$reloaded = new Value((object) $area->toArray());

		$this->assertSame([], $reloaded->street);
		$this->assertTrue($area->equals($reloaded));
	}

	#[Test]
	public function a_complete_address_survives_a_round_trip_through_json(): void
	{
		$original = self::address(['street' => ['Level 3', '7 Cunningham St']]);

		$reloaded = new Value(json_decode((string) json_encode($original->toArray()), false));

		$this->assertTrue($original->equals($reloaded));
	}

	#[Test]
	public function a_subdivision_prefixed_with_another_country_does_not_resolve(): void
	{
		// AU is the country, so US-CA is not a spelling of anything here. It is kept as
		// submitted and `knownSubdivision` reports it — see the test below for why that
		// changed.
		$this->assertSame('US-CA', self::address(['subdivision' => 'US-CA'])->subdivision);
	}

	#[Test]
	public function an_unresolvable_subdivision_is_kept_where_the_country_requires_one(): void
	{
		// It used to raise here and be kept where the country merely *uses* a subdivision,
		// which gave one part three behaviours decided by the country: subdivisionRequired
		// when absent, knownSubdivision when unrecognised in Ireland, and the whole address
		// unreadable when unrecognised in Australia. A bad state is a bad state.
		$this->assertSame('Banana', self::address(['subdivision' => 'Banana'])->subdivision);
	}

	#[Test]
	public function an_unresolvable_subdivision_is_kept_where_the_country_does_not_require_one(): void
	{
		// Ireland uses a county and does not require one, and no Irish subdivision carries its
		// own postcode pattern — so nothing downstream is undecidable and the bad value should
		// be reported by a constraint rather than swallowing the whole address.
		$address = new Value((object) [
			'street' => ['1 Main St'],
			'locality' => 'Carlow',
			'subdivision' => 'Banana',
			'country' => 'IE',
		]);

		$this->assertSame('Banana', $address->subdivision);
	}

	#[Test]
	public function a_resolvable_subdivision_is_canonicalised_where_the_country_does_not_require_one(): void
	{
		$address = new Value((object) [
			'street' => ['1 Main St'],
			'locality' => 'Carlow',
			'subdivision' => 'CW',
			'country' => 'IE',
		]);

		$this->assertSame('IE-CW', $address->subdivision);
	}

	#[Test]
	public function a_subdivision_is_kept_verbatim_where_the_country_has_none_on_file(): void
	{
		// Great Britain's format has no subdivision at all, so there is no list to canonicalise
		// against. The `subdivisionUsed` constraint is what reports it; the value just carries it.
		$address = new Value((object) [
			'street' => ['1 Main St'],
			'locality' => 'London',
			'subdivision' => 'Greater London',
			'postal_code' => 'SW1A 1AA',
			'country' => 'GB',
		]);

		$this->assertSame('Greater London', $address->subdivision);
	}

	// ── equality ───────────────────────────────────────────────────────────────────────────

	#[Test]
	public function two_addresses_with_the_same_parts_are_equal(): void
	{
		$this->assertTrue(self::address()->equals(self::address()));
	}

	#[Test]
	public function street_lines_are_compared_in_order(): void
	{
		$one = self::address(['street' => ['Level 3', '7 Cunningham St']]);
		$other = self::address(['street' => ['7 Cunningham St', 'Level 3']]);

		$this->assertFalse($one->equals($other));
	}

	#[Test]
	public function a_spelling_of_a_country_does_not_make_two_addresses_differ(): void
	{
		$this->assertTrue(
			self::address(['country' => 'AUS'])->equals(self::address(['country' => 'Australia'])),
		);
	}
}
