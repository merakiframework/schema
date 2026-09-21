<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Facade;
use Meraki\Schema\PartScope;
use Meraki\Schema\ValueScope;

// A rule can compare two *fields*, not just a field and a constant — and it can compare one
// named part of each.
//
// A scope names what is being compared:
//
//     #/fields/shipping/value            the whole value
//     #/fields/shipping/value/country    one part of it

$schema = new Facade('checkout');

$schema->add(
	$schema->createAddressField('billing', ['AU', 'NZ']),
	$schema->createAddressField('shipping', ['AU', 'NZ']),
	$schema->createBooleanField('customs_declaration')->makeOptional(),
	$schema->createBooleanField('same_address')->makeOptional(),
);

// The strong question: is the shipping address the billing address, in every part?
$schema->addRule(
	$schema->when(ValueScope::of('shipping'))->equals(ValueScope::of('billing'))
		->then($schema->fields->getByName('same_address')->makeRequired()),
);

// The weaker and more useful one: shipping somewhere else is fine, another country is paperwork.
$schema->addRule(
	$schema->when(PartScope::of('shipping', 'country'))
		->notEquals(PartScope::of('billing', 'country'))
		->then($schema->fields->getByName('customs_declaration')->makeRequired()),
);

$rockhampton = [
	'line1' => '1 Denham St',
	'locality' => 'Rockhampton',
	'administrative_area' => 'QLD',
	'postal_code' => '4700',
	'country' => 'AU',
];

$auckland = [
	'line1' => '1 Queen St',
	'locality' => 'Auckland',
	'administrative_area' => 'AUK',
	'postal_code' => '1010',
	'country' => 'NZ',
];

/** @param array<string, mixed> $shipping */
$report = static function (Facade $schema, string $label, array $billing, array $shipping): void {
	$result = $schema->validate((object) [
		'billing' => (object) $billing,
		'shipping' => (object) $shipping,
	]);

	printf(
		"  %-34s identical: %-5s  customs: %s" . PHP_EOL,
		$label,
		var_export($result->forField('same_address')->wasAlteredByRule(), true),
		var_export($result->forField('customs_declaration')->wasAlteredByRule(), true),
	);
};

echo 'Comparing two addresses:' . PHP_EOL;

$report($schema, 'the very same address', $rockhampton, $rockhampton);
$report($schema, 'same country, different street', $rockhampton, ['line1' => '2 Denham St'] + $rockhampton);
$report($schema, 'a different country', $rockhampton, $auckland);

// Nothing requires the two sides to be the same part, or even the same kind of field — the
// comparison is between two values, and two values that are not alike answer false.
echo PHP_EOL . 'A part is checked when the rule is written, not when it fires:' . PHP_EOL;

try {
	$schema->addRule(
		$schema->when(PartScope::of('billing', 'ctry'))->equals('AU')->then($schema->fields->getByName('same_address')->makeRequired()),
	);
} catch (InvalidArgumentException $e) {
	echo '  ' . $e->getMessage() . PHP_EOL;
}

// Only a value made of named parts can be read into. A text field holds one value, so asking
// for part of it is a mistake rather than an empty answer.
try {
	$schema->addRule(
		$schema->when(PartScope::of('same_address', 'country'))->equals('AU')->then($schema->fields->getByName('billing')->makeRequired()),
	);
} catch (InvalidArgumentException $e) {
	echo '  ' . $e->getMessage() . PHP_EOL;
}
