<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Address;

use Meraki\Schema\Field\MalformedValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A whole address: one that names a country, with every part that country can read.
 *
 * How submitted parts become one is {@see Input}'s, and tested beside this. What is left here is
 * what a whole address is: its parts, its equality, and the form it serialises to and is read back
 * from.
 */
#[Group('field')]
#[CoversClass(Value::class)]
final class ValueTest extends TestCase
{
	private static function address(?array $street = ['7 Cunningham St'], string $country = 'AU'): Value
	{
		return Value::of(street: $street, locality: 'Emerald', subdivision: 'QLD', postalCode: '4720', country: $country);
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
	public function the_part_enum_and_toArray_agree(): void
	{
		$this->assertSame(array_column(Part::cases(), 'value'), array_keys(self::address()->toArray()));
	}

	// ── what it guards ─────────────────────────────────────────────────────────────────────

	#[Test]
	public function an_address_always_names_a_country(): void
	{
		$this->assertSame('AU', (new Value('AU'))->countryCode);

		// A value made by hand cannot hold a country spelled two ways.
		$this->expectException(MalformedValue::class);

		new Value('Australia');
	}

	#[Test]
	public function an_address_written_by_hand_is_read_the_way_a_form_is(): void
	{
		$this->assertSame('AU-QLD', Value::of(locality: 'Emerald', subdivision: 'qld', country: 'Australia')->subdivision);

		$this->expectException(MalformedValue::class);
		$this->expectExceptionMessage('it does not make an address: countryRequired');

		Value::of(locality: 'Emerald');
	}

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
	public function codeFor_resolves_every_iso_3166_1_spelling(string $spelling): void
	{
		$this->assertSame('AU', Value::codeFor($spelling));
	}

	// ── serialising ────────────────────────────────────────────────────────────────────────

	#[Test]
	public function an_absent_street_survives_a_round_trip_through_toArray(): void
	{
		// `toArray()` is the serialisation seam, and a submitted empty list is wrong — so
		// emitting `[]` for an absent street made a value this library produced unreadable to
		// itself. Anything that persists and reloads an address, schema-json included, goes
		// through here.
		$area = Value::of(locality: 'Emerald', subdivision: 'QLD', postalCode: '4720', country: 'AU');

		$reloaded = (new Input((object) $area->toArray()))->value;

		$this->assertSame([], $reloaded?->street);
		$this->assertTrue($area->equals($reloaded));
	}

	#[Test]
	public function a_complete_address_survives_a_round_trip_through_json(): void
	{
		$original = self::address(['Level 3', '7 Cunningham St']);

		$reloaded = (new Input(json_decode((string) json_encode($original->toArray()), false)))->value;

		$this->assertNotNull($reloaded);
		$this->assertTrue($original->equals($reloaded));
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
		$one = self::address(['Level 3', '7 Cunningham St']);
		$other = self::address(['7 Cunningham St', 'Level 3']);

		$this->assertFalse($one->equals($other));
	}

	#[Test]
	public function a_spelling_of_a_country_does_not_make_two_addresses_differ(): void
	{
		$this->assertTrue(self::address(country: 'AUS')->equals(self::address(country: 'Australia')));
	}
}
