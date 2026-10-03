<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Address;

use Meraki\Schema\Exception\BrokenInputContract;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Field\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * An address as it was submitted, read part by part, and what stops the parts making one.
 *
 * Not the same as failing a constraint. A constraint judges an address this field may or may not
 * accept; these are the cases where there is no address to judge yet — a part that was sent but
 * holds nothing, a country that names no country, a postcode the country's own format refuses.
 * No configuration changes any of them.
 */
#[Group('field')]
#[CoversClass(Input::class)]
final class InputTest extends TestCase
{
	/** @param array<string, mixed> $overrides */
	private static function read(array $overrides = [], string ...$without): Input
	{
		$parts = array_diff_key([
			'street' => ['7 Cunningham St'],
			'locality' => 'Emerald',
			'subdivision' => 'QLD',
			'postal_code' => '4720',
			'country' => 'AU',
		], array_flip($without));

		return new Input((object) array_merge($parts, $overrides));
	}

	/** @return list<Check> */
	private static function codes(Input $input): array
	{
		return array_map(static fn(Violation $violation): Check => $violation->code, $input->violations);
	}

	#[Test]
	public function a_whole_address_makes_a_value(): void
	{
		$input = self::read();

		$this->assertSame([], $input->violations);
		$this->assertInstanceOf(Value::class, $input->value);
		$this->assertSame('AU-QLD', $input->value->subdivision);
	}

	#[Test]
	public function the_part_enum_and_parts_agree(): void
	{
		$this->assertSame(array_column(Part::cases(), 'value'), array_keys(self::read()->parts()));
	}

	// ── street is a list ───────────────────────────────────────────────────────────────────

	#[Test]
	public function street_holds_the_lines_as_a_list(): void
	{
		$this->assertSame(['Level 3', '7 Cunningham St'], self::read(['street' => ['Level 3', '7 Cunningham St']])->street);
	}

	#[Test]
	public function an_absent_street_is_an_empty_list(): void
	{
		$this->assertSame([], self::read([], 'street')->street);
	}

	/** @return array<string, array{mixed}> */
	public static function streetsThatAreNotLines(): array
	{
		return [
			'delimited text' => ["Level 3\n7 Cunningham St"],
			'a line that is not text' => [['Level 3', 42]],
			'an empty list' => [[]],
			'an empty line' => [['']],
			'a line of spaces' => [['   ']],
			'a line of newlines' => [["\n\n"]],
			'a line of tabs' => [["\t"]],
			'a record of lines' => [['first' => 'Level 3']],
		];
	}

	#[Test]
	#[DataProvider('streetsThatAreNotLines')]
	public function a_street_that_is_not_a_list_of_lines_is_wrong(mixed $street): void
	{
		// Sent-and-holding-nothing is not the same as not sent: a port with no street leaves the
		// part out. A list because nothing here joins lines into delimited text.
		$input = self::read(['street' => $street]);

		$this->assertSame([Check::StreetFormat], self::codes($input));
		$this->assertSame([], $input->street);
	}

	#[Test]
	public function street_lines_are_kept_exactly_as_submitted(): void
	{
		// No trimming, no collapsing, no line-ending repair: there is no standard normal form
		// for an address line to normalise towards, so tidying is the port's decision.
		$this->assertSame(['  Level 3  ', '7  Cunningham   St'], self::read(['street' => ['  Level 3  ', '7  Cunningham   St']])->street);
	}

	#[Test]
	public function a_fourth_line_is_more_than_any_country_has(): void
	{
		$input = self::read(['street' => ['a', 'b', 'c', 'd']]);

		$this->assertSame([Check::StreetLineLimit], self::codes($input));
		$this->assertSame(3, $input->violations[0]->bound);
		// Read all the same, so a rule about the street sees what was written.
		$this->assertCount(4, $input->street);
	}

	#[Test]
	public function the_line_limit_holds_before_there_is_a_country(): void
	{
		// Every country uses exactly three lines, which is why the limit does not wait for one.
		$this->assertSame(
			[Check::CountryRequired, Check::StreetLineLimit],
			self::codes(self::read(['street' => ['a', 'b', 'c', 'd']], 'country')),
		);
	}

	// ── present but empty is wrong, not absent ─────────────────────────────────────────────

	/** @return array<string, array{string, Check}> */
	public static function everyTextPart(): array
	{
		return [
			'locality' => ['locality', Check::LocalityFormat],
			'subdivision' => ['subdivision', Check::KnownSubdivision],
			'postal_code' => ['postal_code', Check::PostalCodeFormat],
			'country' => ['country', Check::KnownCountry],
			'dependent_locality' => ['dependent_locality', Check::DependentLocalityFormat],
		];
	}

	#[Test]
	#[DataProvider('everyTextPart')]
	public function a_part_sent_as_an_empty_string_is_wrong_rather_than_absent(string $part, Check $code): void
	{
		$input = self::read([$part => '']);

		$this->assertContains($code, self::codes($input));
		$this->assertSame([], $input->missingParts);
	}

	#[Test]
	#[DataProvider('everyTextPart')]
	public function a_part_sent_as_whitespace_is_wrong_rather_than_absent(string $part, Check $code): void
	{
		$this->assertContains($code, self::codes(self::read([$part => '   '])));
	}

	#[Test]
	#[DataProvider('everyTextPart')]
	public function a_part_that_is_not_text_is_wrong(string $part, Check $code): void
	{
		$this->assertContains($code, self::codes(self::read([$part => 42])));
	}

	#[Test]
	public function the_violation_names_the_part_it_is_about(): void
	{
		$this->assertSame(Part::Locality, self::read(['locality' => '   '])->violations[0]->part);
	}

	#[Test]
	public function an_omitted_part_is_absent_rather_than_wrong(): void
	{
		$input = self::read([], 'dependent_locality', 'postal_code');

		$this->assertNull($input->dependentLocality);
		$this->assertNull($input->postalCode);
		$this->assertSame([], $input->violations);
	}

	#[Test]
	public function a_record_with_no_parts_at_all_is_not_an_address(): void
	{
		$this->expectException(MalformedValue::class);

		new Input((object) ['street' => null, 'country' => null]);
	}

	#[Test]
	public function a_key_that_is_not_a_part_cannot_be_read(): void
	{
		// The renames make this earn its keep: anything still sending `line1` would otherwise
		// build an address with no street at all and be told 'street is required', which names
		// the symptom rather than the stale key that caused it.
		//
		// BrokenInputContract rather than a violation, and the difference is who is wrong. A
		// violation is reported, because it came from a submitter. `line1` is a key, and keys
		// are not submitter data in any protocol — so it escapes.
		$this->expectException(BrokenInputContract::class);

		self::read(['line1' => '7 Cunningham St']);
	}

	#[Test]
	public function the_refusal_names_the_key_it_did_not_recognise(): void
	{
		try {
			self::read(['organization' => 'Meraki']);
			$this->fail('expected the address to be refused');
		} catch (BrokenInputContract $e) {
			$this->assertStringContainsString('organization', $e->getMessage());
			$this->assertSame(['organization'], $e->unknownKeys);
		}
	}

	#[Test]
	public function a_part_that_is_not_empty_keeps_its_whitespace(): void
	{
		// ' 4720 ' has content, so it is read — and kept as submitted, so the submitter sees what
		// they sent rather than a silent repair. Australia's pattern is four digits, so it is
		// reported against the postcode.
		$input = self::read(['postal_code' => ' 4720 ']);

		$this->assertSame(' 4720 ', $input->postalCode);
		$this->assertSame([Check::PostalCodeFormat], self::codes($input));
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
		$this->assertSame('AU', self::read(['country' => $spelling])->countryCode);
	}

	/** @return array<string, array{string}> */
	public static function countriesThatAreNot(): array
	{
		return [
			// Everything else is selected by the country — the postcode pattern, the subdivision
			// set, the required parts — so an unrecognised one leaves nothing else decidable.
			'a name nobody has' => ['Banana'],
			'a place that is not a country' => ['Zorbia'],
			// ISO 3166-1 numeric-3 is deliberately not accepted: it is rare in addresses and its
			// leading zeros do not survive a JSON producer that sends it as a number.
			'the numeric code' => ['036'],
		];
	}

	#[Test]
	#[DataProvider('countriesThatAreNot')]
	public function a_country_it_cannot_recognise_is_wrong_and_nothing_else_is_judged(string $country): void
	{
		// A postcode that would fail Australia's pattern is not judged either: there is no
		// country to judge it against, and the country is what is in the way.
		$input = self::read(['country' => $country, 'postal_code' => 'ABC']);

		$this->assertSame([Check::KnownCountry], self::codes($input));
		$this->assertSame([], $input->missingParts);
		$this->assertSame('ABC', $input->postalCode);
	}

	#[Test]
	public function an_address_with_no_country_keeps_its_other_parts(): void
	{
		// Refused until 2.0, on the reasoning that a country gives the rest of an address its
		// meaning. It does — and so does the currency on money, which names the part instead.
		$input = self::read([], 'country');

		$this->assertNull($input->countryCode);
		$this->assertSame('Emerald', $input->locality);
		$this->assertSame([Check::CountryRequired], self::codes($input));
		$this->assertSame([Part::Country], $input->missingParts);
		// Kept as written: there is no country to resolve it against.
		$this->assertSame('QLD', $input->subdivision);
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
		$this->assertSame('AU-QLD', self::read(['subdivision' => $spelling])->subdivision);
	}

	/** @return array<string, array{string, string, string}> */
	public static function subdivisionsByName(): array
	{
		// Every one of these countries keys its subdivisions with a numeric string, which PHP
		// turns into an `int` the moment it becomes an array key. Resolving by name returned
		// that key, and an `int` from a `?string` method is a TypeError — a fatal that escapes
		// validate() entirely rather than arriving as something a caller can act on.
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
		$input = new Input((object) [
			'street' => ['1 Main St'],
			'locality' => 'Somewhere',
			'subdivision' => $name,
			'postal_code' => $country === 'JP' ? '100-0001' : null,
			'country' => $country,
		]);

		$this->assertSame($expected, $input->subdivision);
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
		$input = new Input((object) [
			'street' => ['1 Main St'],
			'locality' => 'Somewhere',
			'subdivision' => $submitted,
			'country' => $country,
		]);

		$this->assertSame($expected, $input->subdivision);
		$this->assertNotContains(Check::KnownSubdivision, self::codes($input));
	}

	/** @return array<string, array{array<string, mixed>, string}> */
	public static function subdivisionsThatAreNotTheCountrys(): array
	{
		return [
			// AU is the country, so US-CA is not a spelling of anything here.
			'another country\'s prefix' => [['subdivision' => 'US-CA'], 'US-CA'],
			// Australia requires a state. A bad state is a bad state whether or not the country
			// requires one: this used to make the whole address unreadable here and be reported
			// in Ireland, which gave one part three behaviours decided by the country.
			'unknown, where one is required' => [['subdivision' => 'Banana'], 'Banana'],
			'unknown, where one is optional' => [['street' => ['1 Main St'], 'locality' => 'Carlow', 'subdivision' => 'Banana', 'postal_code' => null, 'country' => 'IE'], 'Banana'],
		];
	}

	/** @param array<string, mixed> $overrides */
	#[Test]
	#[DataProvider('subdivisionsThatAreNotTheCountrys')]
	public function an_unresolvable_subdivision_is_kept_and_reported(array $overrides, string $kept): void
	{
		$input = self::read($overrides);

		$this->assertSame($kept, $input->subdivision);
		$this->assertSame([Check::KnownSubdivision], self::codes($input));
	}

	#[Test]
	public function a_resolvable_subdivision_is_canonicalised_where_the_country_does_not_require_one(): void
	{
		$input = new Input((object) [
			'street' => ['1 Main St'],
			'locality' => 'Carlow',
			'subdivision' => 'CW',
			'country' => 'IE',
		]);

		$this->assertSame('IE-CW', $input->subdivision);
	}

	#[Test]
	public function a_subdivision_the_country_has_no_place_for_is_kept_and_reported(): void
	{
		// Great Britain's format has no subdivision at all, so there is no list to canonicalise
		// against, and a state typed for a country with no states is a mistake worth reporting
		// rather than data to quietly ignore.
		$input = new Input((object) [
			'street' => ['1 Main St'],
			'locality' => 'London',
			'subdivision' => 'Greater London',
			'postal_code' => 'SW1A 1AA',
			'country' => 'GB',
		]);

		$this->assertSame('Greater London', $input->subdivision);
		$this->assertSame([Check::SubdivisionUsed], self::codes($input));
	}

	// ── what a rule compares against ───────────────────────────────────────────────────────

	#[Test]
	public function an_expectation_is_read_the_way_the_part_was_stored(): void
	{
		$input = self::read();

		$this->assertSame('AU', $input->canonicalPartValue(Part::Country, 'Australia'));
		$this->assertSame('AU-QLD', $input->canonicalPartValue(Part::Subdivision, 'qld'));
		$this->assertSame('Emerald', $input->canonicalPartValue(Part::Locality, 'Emerald'));
	}

	#[Test]
	public function a_subdivision_is_compared_as_written_until_there_is_a_country(): void
	{
		$this->assertSame('QLD', self::read([], 'country')->canonicalPartValue(Part::Subdivision, 'QLD'));
	}
}
