<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Definition;

// A rule that applies to each row of a collection on its own.
//
// "If this attendee is a child, require this attendee's guardian" is a question about one row at a
// time. A schema rule cannot ask it — naming the collection only ever speaks about the list as a
// whole — so a collection carries its own rules, applied per row.

$schema = new Definition('workshop');

$age = $schema->createNumberField('age');
$guardian = $schema->createTextField('guardian')->makeOptional();

// A row rule's world is the template, so it is written exactly like any other rule: `$age->when()`
// means "this row's age", because the row's field set *is* the template.
$attendees = $schema->createCollectionField('attendees', $age, $guardian)
	->forEachRow($age->when()->isLessThan(18)->then($guardian->makeRequired()));

$schema->add($attendees);

$result = $schema->validate((object) ['attendees' => [
	'child' => (object) ['age' => '9', 'guardian' => null],
	'adult' => (object) ['age' => '34', 'guardian' => null],
	'teen' => (object) ['age' => '15', 'guardian' => 'Sam Okafor'],
]]);

$rows = $result->forField('attendees');

echo 'PER ROW' . PHP_EOL;

foreach ($rows->items as $name => $row) {
	$g = $row->forField('guardian');

	printf(
		'  %-6s age %-3s guardian %-9s %s' . PHP_EOL,
		$name,
		(string) $row->forField('age')->value,
		$g->field->optional ? 'optional' : 'required',
		$g->anyFailed() ? 'FAILED — nobody named' : 'ok',
	);
}

// Each row folds over its own copy of the template, so the rule that fired for `child` said nothing
// about `adult`. If it could reach the shared template, the first child in a list would make a
// guardian required for everyone after them.
echo PHP_EOL . 'The authored template is untouched: guardian is '
	. ($attendees->template[1]->optional ? 'optional' : 'required') . PHP_EOL;

// And a rule records what it did, per row.
echo PHP_EOL . 'WHY' . PHP_EOL;

foreach ($rows->items as $name => $row) {
	foreach ($row->forField('guardian')->appliedOutcomes as $applied) {
		printf('  %-6s %s' . PHP_EOL, $name, json_encode($applied->outcome->changes));
	}
}

// ── asking about the rows collectively ────────────────────────────────────────────────────

// A column addresses one template field across every row, and needs a quantifier to say how many
// of them have to match: one is enough for `whereAny`, all of them for `whereEvery`.
$order = new Definition('order');

$sku = $order->createTextField('sku');
$declaration = $order->createTextField('declaration')->makeOptional();
$lines = $order->createCollectionField('lines', $sku);

$order->add($lines, $declaration);
$order->addRule($lines->whereAny('sku')->equals('HAZMAT')->then($declaration->makeRequired()));

echo PHP_EOL . 'ANY ROW' . PHP_EOL;

foreach ([
	'one hazardous line' => ['a' => (object) ['sku' => 'HAZMAT'], 'b' => (object) ['sku' => 'NORMAL']],
	'nothing hazardous' => ['a' => (object) ['sku' => 'NORMAL'], 'b' => (object) ['sku' => 'NORMAL']],
] as $label => $rows) {
	$verdict = $order->validate((object) ['lines' => $rows]);

	printf(
		'  %-20s declaration %s' . PHP_EOL,
		$label,
		$verdict->forField('declaration')->field->optional ? 'optional' : 'required',
	);
}
