<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Facade;

// Every field parses to a value object this library defines, and that object decides what counts
// as the same value.
//
// It matters because PHP's `==` compares two objects property by property — so it reads the
// *private layout* of whatever class a field happens to return. For a third-party class that is
// not a contract; it is an implementation detail that happens to be visible.

$schema = new Facade('comparisons');

$schema->add(
	$schema->createNumberField('qty'),
	$schema->createUuidField('reference'),
	$schema->createPhoneNumberField('phone', ['AU']),
	$schema->createDurationField('length'),
);

/** Two submissions of the same field, and whether the field calls them one value. */
$same = static function (Facade $schema, string $field, mixed $a, mixed $b): string {
	$one = $schema->fields->getByName($field)->resolvedValueFor($a);
	$other = $schema->fields->getByName($field)->resolvedValueFor($b);

	return $one->equals($other) ? 'same' : 'different';
};

echo 'Written two ways, and the same value either way:' . PHP_EOL;

// A BigDecimal keeps the scale it was given, so `==` would call these different numbers.
printf("  qty        %-22s %-22s %s" . PHP_EOL, '12.50', '12.5', $same($schema, 'qty', '12.50', '12.5'));

// RFC 9562 says a UUID's hex digits may be written in either case.
printf(
	"  reference  %-22s %-22s %s" . PHP_EOL,
	'3F2504E0-...-3301',
	'3f2504e0-...-3301',
	$same($schema, 'reference', '3F2504E0-4F89-41D3-9A0C-0305E82C3301', '3f2504e0-4f89-41d3-9a0c-0305e82c3301'),
);

// One number, two spellings. libphonenumber's own object carries the raw input alongside the
// parsed number, so `==` would say these are different.
printf(
	"  phone      %-22s %-22s %s" . PHP_EOL,
	'0411 222 333',
	'+61411222333',
	$same(
		$schema,
		'phone',
		(object) ['number' => '0411 222 333', 'country' => 'AU'],
		(object) ['number' => '+61411222333', 'country' => 'AU'],
	),
);

printf("  length     %-22s %-22s %s" . PHP_EOL, 'PT1H', 'PT60M', $same($schema, 'length', 'PT1H', 'PT60M'));

echo PHP_EOL . 'And genuinely different values stay different:' . PHP_EOL;

printf("  qty        %-22s %-22s %s" . PHP_EOL, '12.50', '12.51', $same($schema, 'qty', '12.50', '12.51'));

echo PHP_EOL . 'Some values are ordered as well as equatable:' . PHP_EOL;

$cheap = $schema->fields->getByName('qty')->resolvedValueFor('5');
$dear = $schema->fields->getByName('qty')->resolvedValueFor('12.50');

// An Order enum rather than -1/0/1, so a caller never has to remember the sign convention.
echo '  5 vs 12.50   ' . $cheap->compareTo($dear)->name . PHP_EOL;
echo '  12.50 vs 5   ' . $dear->compareTo($cheap)->name . PHP_EOL;
echo '  isAtLeast?   ' . var_export($dear->compareTo($cheap)->isAtLeast(), true) . PHP_EOL;

echo PHP_EOL . 'Ordering across kinds is not a question with an answer:' . PHP_EOL;

try {
	$dear->compareTo($schema->fields->getByName('length')->resolvedValueFor('PT1H'));
} catch (InvalidArgumentException $e) {
	echo '  ' . $e->getMessage() . PHP_EOL;
}

// Equality, unlike ordering, always has an answer — so it does not raise.
echo '  equals()?    ' . var_export(
	$dear->equals($schema->fields->getByName('length')->resolvedValueFor('PT1H')),
	true,
) . PHP_EOL;
