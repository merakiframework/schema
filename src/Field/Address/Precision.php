<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Address;

use Meraki\Schema\Exception\UnknownAddressPart;

/**
 * How much of the address hierarchy a field demands.
 *
 * Replaces {@see Type}, which tried to span two independent axes at once — how deep an address
 * is specified, and whether it names a place a person can attend — in one closed set of "kinds".
 * That is why `Postal` had nothing to do at request time and why no third name sat comfortably
 * beside the other two. Depth is ordered, and lives here; attendability is a separate opt-in on
 * the field.
 *
 * The rungs are the address hierarchy itself:
 *
 *     Country  <  Subdivision  <  Locality  <  Street
 *
 * A field's floor filters the country's own required parts: whatever the country asks for,
 * minus the parts below the floor. So the ladder never invents a requirement — it only declines
 * to inherit one — and a shallower floor still *accepts* a deeper value, which is what makes
 * "an address or an area" a single field rather than a union.
 *
 * A postcode is not a rung of its own. It identifies a delivery zone rather than a tier between
 * locality and street, and "Emerald QLD 4720" is one answer, so it travels with the locality.
 */
enum Precision: string
{
	case Country = 'country';
	case Subdivision = 'subdivision';
	case Locality = 'locality';
	case Street = 'street';

	/**
	 * Which tier each part belongs to, deepest last.
	 *
	 * `organization`, `given_name` and the rest are absent because this library does not model
	 * them: an address identifies a place, not who is at it.
	 *
	 * @var array<string, int>
	 */
	private const TIERS = [
		'country' => 0,
		'subdivision' => 1,
		'locality' => 2,
		'dependent_locality' => 2,
		'postal_code' => 2,
		'street' => 3,
	];

	/**
	 * Whether this floor leaves the part in reach, and so eligible to be required.
	 *
	 * "Eligible", not "required": the country still decides. A floor of {@see self::Locality} in
	 * Panama leaves `postal_code` in reach and Panama asks for none, so nothing requires it.
	 *
	 * @throws UnknownAddressPart when the part is not one an address has.
	 */
	public function covers(string $part): bool
	{
		if (!isset(self::TIERS[$part])) {
			throw UnknownAddressPart::notOnTheLadder($part, array_keys(self::TIERS));
		}

		return self::TIERS[$part] <= self::TIERS[$this->value];
	}

	/**
	 * Every part an address has, shallowest first.
	 *
	 * @return list<string>
	 */
	public static function parts(): array
	{
		return array_keys(self::TIERS);
	}
}
