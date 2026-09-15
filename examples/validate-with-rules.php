<?php
/**
 * Conditional rules: whether one field is required depends on another field's value.
 *
 * Run: php examples/validate-with-rules.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Facade;
use Meraki\Schema\Field\ShapeValidationResult;
use Meraki\Schema\SchemaValidationResult;

function report(string $label, SchemaValidationResult $result): void
{
	echo $label . PHP_EOL;

	if (!$result->anyFailed()) {
		echo '  valid' . PHP_EOL . PHP_EOL;

		return;
	}

	foreach ($result->getFailed() as $field) {
		foreach ($field->getFailed() as $failure) {
			// Three kinds of failure, and the field says which without anything here having to
			// re-inspect what was submitted to work it out.
			$why = match (true) {
				!$failure instanceof ShapeValidationResult => $failure->name,
				$failure->wasMissing() => 'is required, and nothing was supplied',
				default => 'could not be read as this kind of value',
			};

			printf('  %-24s %s' . PHP_EOL, $field->field->name, $why);
		}
	}

	echo PHP_EOL;
}

// for() declares the acceptable countries once on the *factory*, so every address it builds
// carries the same list without repeating it. It says which countries are allowed — the address
// still has to name the one it is in, the way an amount of money has to name its currency.

$schema = new Facade('booking');

$hasLogBook = $schema->createBooleanField('has_log_book')->defaultsTo(true);
$timeCompleted = $schema->createDurationField('log_book_time_completed')
	->makeOptional()
	->minValueOf('PT0M')
	->maxValueOf('PT200H');

$schema->add(
	$schema->createUuidField('id')->allowVersions(7),
	$schema->createNameField('full_name')->minLengthOf(1)->maxLengthOf(255),
	$schema->createTextField('licence_number')->minLengthOf(1)->maxLengthOf(255),
	$schema->createAddressField('pickup_location'),
	$schema->createEnumField('transmission_type', ['automatic', 'manual'])->defaultsTo('automatic'),
	$hasLogBook,
	$timeCompleted,
);

// Keeping a log book means the completed time has to be supplied — and not keeping one means it
// is not merely optional but irrelevant, so anything submitted for it is discarded.
//
// Both halves are one rule with one condition. Written as two rules with hand-inverted
// conditions they could drift apart, and nothing would notice.
$schema->addRule(
	$schema->when($hasLogBook)->equals(true)
		->thenRequire($timeCompleted)
		->otherwiseIgnore($timeCompleted)
);

// An object, because a payload is a record of named fields — and `pickup_location` is a record
// of parts, so it is one too. Arrays are for lists.
$booking = (object) [
	'id' => '017f22e2-79b0-7cc3-98c4-dc0c0c07398f',
	'full_name' => 'Jane Doe',
	'licence_number' => 'QLD-1234567',
	'pickup_location' => (object) [
		'line1' => '1 Queen St',
		'locality' => 'Brisbane',
		'administrative_area' => 'QLD',
		'postal_code' => '4000',
		'country' => 'AU',
	],
	'has_log_book' => true,
	'transmission_type' => 'automatic',
];

/** The same booking with one field changed — a record is cloned rather than unioned. */
$withChange = static function (string $field, mixed $value) use ($booking): object {
	$changed = clone $booking;
	$changed->{$field} = $value;

	return $changed;
};

// The rule fires and requires the duration, which is missing — so this fails even though every
// value that *was* supplied is well-formed.
report('A log book, but no time recorded:', $schema->validate($booking));

// Supply it and the same schema passes.
report('The same booking with the time:', $schema->validate($withChange('log_book_time_completed', 'PT10H')));

// No log book, so the else-branch discards whatever was sent for the duration. A stale value
// left behind by a form that stopped showing the field cannot fail the request.
$noLogBook = $withChange('has_log_book', false);
$noLogBook->log_book_time_completed = 'not-a-duration';

report('No log book, and a stale duration:', $schema->validate($noLogBook));

// Validating repeatedly applies the rules repeatedly against the same shared schema. That it
// gives the same answer every time is the guarantee a long-lived worker depends on.
$twice = $schema->validate($booking)->anyFailed() === $schema->validate($booking)->anyFailed();

echo 'Same answer when validated twice: ' . var_export($twice, true) . PHP_EOL;
