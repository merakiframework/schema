<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Facade;

// A repeatable section: many rows, each made of the same fields.
//
// The template is handed over as ordinary fields. Every row is checked against all of them, and
// the collection itself is checked against its own constraints — how many rows, and whether any
// repeat.

$schema = new Facade('invoice');

$schema->add(
	$schema->createCollectionField(
		'lines',
		$schema->createTextField('sku'),
		$schema->createNumberField('qty'),
	)->minCountOf(1),
);

// The collection is an **array**, because it holds many of something. Each row is an **object**,
// because a row is one record with named parts. That distinction is what lets a row be *named*.
$result = $schema->validate((object) [
	'lines' => [
		'first run' => (object) ['sku' => 'A1', 'qty' => '2'],
		'second run' => (object) ['sku' => 'B2', 'qty' => '3'],
	],
]);

$lines = $result->forField('lines');

echo "PASSES" . PHP_EOL;
echo '  status: ' . $lines->status->name . PHP_EOL;

// A row keeps the key it arrived under, so a failure can be reported against something a person
// recognises rather than "row 2".
foreach ($lines->value as $key => $row) {
	printf("  %-12s sku=%s qty=%s" . PHP_EOL, $key, $row->sku->text, $row->qty->number);
}

echo PHP_EOL . 'FAILS — a duplicated row' . PHP_EOL;

// `unique` is on by default: a list of things is usually a *set* of things, and the same line
// twice is a mistake far more often than it is intent. Note that `2.0` and `2` are the same
// quantity here — the comparison asks the value, and a number written two ways is one number.
$repeated = $schema->validate((object) [
	'lines' => [
		(object) ['sku' => 'A1', 'qty' => '2.0'],
		(object) ['sku' => 'A1', 'qty' => '2'],
	],
]);

echo '  unique: ' . $repeated->forField('lines')->forConstraint('unique')->status->name . PHP_EOL;

echo PHP_EOL . 'FAILS — one bad row, and the collection fails with it' . PHP_EOL;

$bad = $schema->validate((object) [
	'lines' => [
		(object) ['sku' => 'A1', 'qty' => '2'],
		(object) ['sku' => 'B2', 'qty' => 'three'],
	],
]);

$lines = $bad->forField('lines');

echo '  collection failed: ' . var_export($lines->anyFailed(), true) . PHP_EOL;

// ...but its *own* verdicts are untouched. "How many rows" and "row 1 is wrong" are different
// questions and neither is flattened into the other.
echo '  minCount:          ' . $lines->forConstraint('minCount')->status->name . PHP_EOL;

foreach ($lines->failedItems as $item) {
	$qty = $item->forField('qty');

	printf("  row %-3s qty %-8s %s" . PHP_EOL, $item->key, var_export($qty->given, true), $qty->shape->status->name);
}
