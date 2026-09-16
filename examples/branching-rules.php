<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Facade;

// A rule makes one field's requirements depend on another field's value.
//
// Both branches live on the same rule. Writing the else-branch as a *second* rule with a
// hand-inverted condition means two conditions that are supposed to be opposites, with nothing
// checking that they stay so — a change to one is silently a change in meaning.

$schema = new Facade('booking');

$schema->add(
	$schema->createEnumField('who_for', ['myself', 'someone_else']),
	$schema->createNameField('participant_name'),
	$schema->createEmailAddressField('participant_email'),
);

$schema->addRule(
	$schema->when('who_for')->equals('someone_else')
		->thenRequire('participant_name')
		->thenRequire('participant_email')
		->elseMakeOptional('participant_name')
		->elseMakeOptional('participant_email'),
);

/** @param array<string, mixed> $submitted */
$report = static function (Facade $schema, string $label, array $submitted): void {
	$result = $schema->validate((object) $submitted);

	echo $label . PHP_EOL;

	foreach (['participant_name', 'participant_email'] as $name) {
		$field = $result->forField($name);

		printf(
			"  %-18s required: %-5s  %-7s  %s" . PHP_EOL,
			$name,
			var_export(!$field->field->optional, true),
			$field->status->name,
			// Whether a rule did this, rather than the author writing it that way. A renderer
			// needs to tell those apart: a field made optional by a rule should not be drawn as
			// though the author made it optional.
			$field->wasAlteredByRule() ? 'by rule' : 'as authored',
		);
	}

	echo PHP_EOL;
};

$report($schema, 'Booking for yourself — the participant fields are not asked for:', [
	'who_for' => 'myself',
]);

$report($schema, 'Booking for someone else, with nothing filled in — now they are:', [
	'who_for' => 'someone_else',
]);

$report($schema, 'Booking for someone else, filled in:', [
	'who_for' => 'someone_else',
	'participant_name' => 'Kim Miller',
	'participant_email' => 'kim@example.test',
]);

// A condition can be held in a variable and used for more than one rule. Attaching an outcome
// hands back a *copy*, so the two rules stay separate.
echo 'One condition, two independent rules:' . PHP_EOL;

$forSomeoneElse = $schema->when('who_for')->equals('someone_else');

echo '  outcomes on the first : ' . count($forSomeoneElse->thenRequire('participant_name')->build()->outcomes) . PHP_EOL;
echo '  outcomes on the second: ' . count($forSomeoneElse->thenRequire('participant_email')->build()->outcomes) . PHP_EOL;

// Several conditions can be combined. allOf() takes *conditions*, never finished rules: the
// outcomes attach to the combined result, so there is exactly one set of them.
echo PHP_EOL . 'Combining conditions:' . PHP_EOL;

$combined = new Facade('combined');
$combined->add(
	$combined->createEnumField('who_for', ['myself', 'someone_else']),
	$combined->createEnumField('who_manages', ['organiser', 'participant']),
	// Optional as authored, so the rule requiring it is something you can actually see. A field
	// is required unless it says otherwise, so `thenRequire` on an already-required field is a
	// no-op — which is a fine way to write an example that proves nothing.
	$combined->createEmailAddressField('participant_email')->makeOptional(),
);

$combined->addRule(
	$combined->allOf(
		$combined->when('who_for')->equals('someone_else'),
		$combined->when('who_manages')->equals('participant'),
	)->thenRequire('participant_email'),
);

foreach ([['someone_else', 'participant'], ['someone_else', 'organiser'], ['myself', 'participant']] as [$for, $manages]) {
	$email = $combined->validate((object) ['who_for' => $for, 'who_manages' => $manages])->forField('participant_email');

	printf("  %-14s %-12s email required: %s" . PHP_EOL, $for, $manages, var_export(!$email->field->optional, true));
}
