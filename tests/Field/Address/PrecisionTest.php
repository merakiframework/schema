<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Address;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * How much of the address hierarchy a field demands.
 *
 * The ladder replaced {@see Type}, which tried to span two independent axes — how deep an
 * address is specified, and whether it names a place you can attend — in one closed set of
 * "kinds". Depth is ordered and lives here; attendability is a separate opt-in on the field.
 */
#[Group('field')]
#[CoversClass(Precision::class)]
final class PrecisionTest extends TestCase
{
	#[Test]
	public function it_names_each_rung_of_the_address_hierarchy(): void
	{
		$this->assertSame('country', Precision::Country->value);
		$this->assertSame('subdivision', Precision::Subdivision->value);
		$this->assertSame('locality', Precision::Locality->value);
		$this->assertSame('street', Precision::Street->value);
	}

	#[Test]
	public function it_has_exactly_four_rungs(): void
	{
		$this->assertCount(4, Precision::cases());
	}

	/**
	 * @return array<string, array{Precision, string, bool}>
	 */
	public static function coverage(): array
	{
		return [
			// Street: the whole ladder is in reach.
			'street covers street' => [Precision::Street, 'street', true],
			'street covers locality' => [Precision::Street, 'locality', true],
			'street covers postal_code' => [Precision::Street, 'postal_code', true],
			'street covers dependent_locality' => [Precision::Street, 'dependent_locality', true],
			'street covers subdivision' => [Precision::Street, 'subdivision', true],
			'street covers country' => [Precision::Street, 'country', true],

			// Locality: the street tier drops out, its own tier stays.
			'locality drops street' => [Precision::Locality, 'street', false],
			'locality covers locality' => [Precision::Locality, 'locality', true],
			'locality covers postal_code' => [Precision::Locality, 'postal_code', true],
			'locality covers dependent_locality' => [Precision::Locality, 'dependent_locality', true],
			'locality covers subdivision' => [Precision::Locality, 'subdivision', true],
			'locality covers country' => [Precision::Locality, 'country', true],

			// Subdivision: everything below it drops, including the postcode.
			'subdivision drops street' => [Precision::Subdivision, 'street', false],
			'subdivision drops locality' => [Precision::Subdivision, 'locality', false],
			'subdivision drops postal_code' => [Precision::Subdivision, 'postal_code', false],
			'subdivision drops dependent_locality' => [Precision::Subdivision, 'dependent_locality', false],
			'subdivision covers subdivision' => [Precision::Subdivision, 'subdivision', true],
			'subdivision covers country' => [Precision::Subdivision, 'country', true],

			// Country: nothing below the country survives.
			'country drops street' => [Precision::Country, 'street', false],
			'country drops locality' => [Precision::Country, 'locality', false],
			'country drops postal_code' => [Precision::Country, 'postal_code', false],
			'country drops subdivision' => [Precision::Country, 'subdivision', false],
			'country covers country' => [Precision::Country, 'country', true],
		];
	}

	#[Test]
	#[DataProvider('coverage')]
	public function it_says_which_parts_a_floor_leaves_in_reach(Precision $floor, string $part, bool $covered): void
	{
		$this->assertSame($covered, $floor->covers($part));
	}

	#[Test]
	public function the_postcode_sits_with_the_locality_rather_than_on_a_rung_of_its_own(): void
	{
		// A postcode identifies a delivery zone, not a tier between locality and street:
		// "Emerald QLD 4720" is one answer, so the two travel together.
		foreach (Precision::cases() as $floor) {
			$this->assertSame(
				$floor->covers('locality'),
				$floor->covers('postal_code'),
				"{$floor->value} should treat postal_code and locality alike",
			);
		}
	}

	#[Test]
	public function it_refuses_a_part_that_is_not_on_the_ladder(): void
	{
		// A typo in the part-name mapping is a bug, not an address that needs nothing.
		$this->expectException(InvalidArgumentException::class);

		Precision::Street->covers('organization');
	}
}
