<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Facade;
use Meraki\Schema\Field\Address\Precision;

// An address field has two dials, and they answer independent questions:
//
//   - how far down the address hierarchy the field demands  -> minPrecisionOf()
//   - whether it must name a place a person can attend      -> mustBeVisitable()
//
// Neither invents a requirement. What a country asks for comes from that country's own
// published format, and the floor only decides how much of it to inherit — so the same field
// is correct in Australia, Japan and Panama without knowing anything about them.

$schema = new Facade('venues');

// The default: a street is required, and a PO box is a perfectly good answer.
$billing = $schema->createAddressField('billing');

// Somewhere a person or a vehicle can actually go.
$pickup = $schema->createAddressField('pickup')->mustBeVisitable();

// A service area or catchment, where the region *is* the answer rather than an incomplete
// version of one.
$serviceArea = $schema->createAddressField('service_area')
	->minPrecisionOf(Precision::Locality);

// A tax or licensing jurisdiction.
$jurisdiction = $schema->createAddressField('tax_jurisdiction')
	->minPrecisionOf(Precision::Subdivision);

// "Where are you based" — nothing below the country is asked for.
$origin = $schema->createAddressField('incorporated_in')
	->minPrecisionOf(Precision::Country);

// Locality depth, but a street that *is* given must not be a delivery receptacle. The two
// dials are independent, so this combination costs nothing extra to express.
$whereabouts = $schema->createAddressField('whereabouts')
	->minPrecisionOf(Precision::Locality)
	->mustBeVisitable();

$schema = $schema->add($billing, $pickup, $serviceArea, $jurisdiction, $origin, $whereabouts);

/**
 * An address is submitted as a record of its parts. A part with no value is **omitted** —
 * never sent as `''`, which is a value that was provided and cannot be read.
 *
 * @param array<string, mixed> $overrides
 */
function emerald(array $overrides = [], string ...$without): object
{
	$parts = array_diff_key([
		// One part, holding a list of lines. Not `line1`/`line2`: WHATWG's `street-address`
		// token describes exactly this, and a delimited string would carry a separator whose
		// spelling HTML and JSON disagree on.
		'street' => ['7 Cunningham St'],
		'locality' => 'Emerald',
		// ISO 3166-2's word, and its codes. `QLD`, `qld`, `AU-QLD` and `Queensland` all
		// resolve; all four are stored as `AU-QLD`.
		'subdivision' => 'QLD',
		'postal_code' => '4720',
		// `AU`, `AUS` and `Australia` are the same country, in any case.
		'country' => 'AU',
	], array_flip($without));

	return (object) array_merge($parts, $overrides);
}

$show = static function (string $label, \Meraki\Schema\Field $field, object $address): void {
	$result = $field->validate($address);
	$failed = [];

	foreach ($result->constraintNames as $name) {
		if ($result->forConstraint($name)->failed()) {
			$failed[] = $name;
		}
	}

	printf(
		"  %-42s %-8s %s\n",
		$label,
		$result->shape->failed() ? 'UNREADABLE' : ($failed === [] ? 'valid' : 'invalid'),
		implode(', ', $failed),
	);
};

echo "What each field makes of an address\n\n";

$show('billing, complete', $billing, emerald());
$show('billing, a PO box', $billing, emerald(['street' => ['PO Box 5']]));
$show('billing, street only', $billing, emerald([], 'locality', 'subdivision', 'postal_code'));
$show('pickup, a PO box', $pickup, emerald(['street' => ['PO Box 5']]));
$show('pickup, box on the second line', $pickup, emerald(['street' => ['Level 3', 'PO Box 5']]));
$show('service_area, no street', $serviceArea, emerald([], 'street'));
$show('service_area, with a street', $serviceArea, emerald());
$show('jurisdiction, state only', $jurisdiction, emerald([], 'street', 'locality', 'postal_code'));
$show('incorporated_in, country only', $origin, emerald([], 'street', 'locality', 'subdivision', 'postal_code'));
$show('whereabouts, area with a PO box', $whereabouts, emerald(['street' => ['PO Box 5']]));

echo "\nThe same field, judged by each country's own rules\n\n";

// Nothing here is configured per country. Japan addresses by prefecture and does not require a
// locality; Panama has no postcode at all; Great Britain has no subdivision. A check for a part
// the submitted country does not ask for *skips* — it does not quietly pass.
$show('JP: prefecture and postcode, no locality', $billing, (object) [
	'street' => ['1-1 Chiyoda'],
	'subdivision' => 'JP-13',
	'postal_code' => '100-0001',
	'country' => 'JP',
]);

$show('PA: locality, no postcode', $billing, (object) [
	'street' => ['Calle 50'],
	'locality' => 'Ciudad de Panama',
	'country' => 'PA',
]);

$show('GB: no subdivision to give', $billing, (object) [
	'street' => ['10 Downing St'],
	'locality' => 'London',
	'postal_code' => 'SW1A 2AA',
	'country' => 'GB',
]);

// A part the country's format has no place for is reported rather than ignored, because
// accepting it would mean accepting data this library cannot check.
$show('GB: a county it has no place for', $billing, (object) [
	'street' => ['10 Downing St'],
	'locality' => 'London',
	'subdivision' => 'Greater London',
	'postal_code' => 'SW1A 2AA',
	'country' => 'GB',
]);

// Australia has no dependent locality: a "suburb" here *is* the locality. Cardiff NSW 2285 is
// complete as it stands, and Newcastle — the city Cardiff sits in — is not part of the address.
$show('AU: Cardiff NSW 2285', $billing, (object) [
	'street' => ['12 Macquarie Rd'],
	'locality' => 'Cardiff',
	'subdivision' => 'NSW',
	'postal_code' => '2285',
	'country' => 'AU',
]);

$show('AU: Newcastle as a dependent locality', $billing, (object) [
	'street' => ['12 Macquarie Rd'],
	'locality' => 'Cardiff',
	'dependent_locality' => 'Newcastle',
	'subdivision' => 'NSW',
	'postal_code' => '2285',
	'country' => 'AU',
]);

echo "\nWhat a port asks before anyone has submitted anything\n\n";

// Every country-driven bound is unanswerable while more than one country is allowed — and
// allowing any is the default — so a form with a country selector cannot mark its inputs
// required from the field's properties alone. requirementsFor() is the one read path.
//
// The key is the spelling you asked with, so a caller holding alpha-3 gets its own vocabulary
// back along with the canonicalisation table it would otherwise have to build.
foreach ($billing->requirementsFor('AUS', 'japan', 'PA') as $asked => $rules) {
	$pattern = $rules->postalCodeFormat ?? '(none)';

	printf(
		"  asked %-8s -> %s  required: %-46s lines: %d  postcode: %s\n",
		$asked,
		$rules->country,
		implode(', ', $rules->requiredParts),
		$rules->streetLineLimit,
		// Great Britain's runs to 500 characters, which is a fact about postcodes rather than
		// about this library, but it does not belong in a demonstration.
		strlen($pattern) > 30 ? substr($pattern, 0, 27) . '...' : $pattern,
	);
}

// The floor filters what a country requires; it never touches what a country *uses*.
$au = $serviceArea->requirementsFor('AU')['AU'];

printf("\n  service_area in AU -> required: %s\n", implode(', ', $au->requiredParts));
printf("                        used:     %s\n", implode(', ', $au->usedParts));
printf("                        states:   %s\n", implode(', ', $au->subdivisions));

// A field restricted to one country need not repeat it.
$delivery = (new Facade('shipping'))->createAddressField('delivery', ['AU']);

printf("\n  delivery (AU only) -> %s\n", implode(', ', $delivery->requirementsFor()['AU']->requiredParts));

echo "\n";
