<?php
declare(strict_types=1);

/**
 * Booking a workshop: who is organising it, where it runs, who is coming, and what it costs.
 *
 * Shows the whole shape of the library in one go — building field definitions, adding them to a
 * schema, and reading the result — with a `Collection` doing the work it exists for.
 *
 * Run it:
 *
 *     php examples/workshop-booking.php          # a booking that should pass
 *     php examples/workshop-booking.php --bad    # the same booking, with things wrong in it
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Facade;
use Meraki\Schema\Field;

/**
 * The schema is built once and shared. Nothing per-request is written back to it, so the same
 * object can serve every booking the process handles.
 */
function workshopSchema(): Facade
{
	// The schema builds its own fields, the same way it builds its own rules. `for('AU')` says
	// which countries the region-aware fields accept, so they need not each repeat it.
	$schema = (new Facade('workshop_booking'))->for('AU');

	$schema->add(
		$schema->createNameField('organiser')
			->minLengthOf(2)
			->maxLengthOf(70),

		$schema->createEmailAddressField('contact_email'),

		// A workshop happens somewhere you can walk into, so a PO box is not an answer.
		// Restrictions narrow rather than replace: an address names a street by default, and
		// allowOnlyPhysical() adds "and it must be visitable" on top.
		$schema->createAddressField('venue')->allowOnlyPhysical(),

		// The collection. Its *template* is the fields one item is made of, handed over like any
		// other argument — so a booking comes back as
		// [['name' => …, 'email' => …, 'dietary' => …], …].
		//
		// Unique by default: a list of attendees is a set of attendees, and the same person
		// entered twice is a mistake rather than an instruction.
		//
		// Note what is *not* here: a way to discard blank rows. A form rendered with spare rows
		// sends them empty, and stripping those is the renderer's job — it is the only thing that
		// can tell a spare row from one somebody started and abandoned. This example submits the
		// rows a port would already have cleaned.
		$schema->createCollectionField(
			'attendees',
			$schema->createNameField('name')->minLengthOf(2),
			$schema->createEmailAddressField('email'),
			$schema->createTextField('dietary')->maxLengthOf(120)->makeOptional(),
		)
			->minCountOf(2)
			->maxCountOf(12),

		// Money carries its currency, because 250 means nothing until you know which.
		// The scale comes from ISO 4217 — AUD has two decimal places — so naming the currency
		// is enough.
		$schema->createMoneyField('deposit', ['AUD'])
			->minAmountOf('AUD', '50.00')
			->maxAmountOf('AUD', '2000.00'),

		$schema->createBooleanField('terms_accepted')->mustBeAccepted(),
	);

	return $schema;
}

/**
 * A booking that satisfies every rule above.
 *
 * Note the two shapes, and the rule behind them: **an object is a record, an array is a list.**
 * The booking is a record of named fields, so it is an object; `venue` and `deposit` are records
 * of parts, so they are too; `attendees` holds many of something, so it is an array — of objects.
 *
 * PHP cannot tell an associative array from a list, so nothing else could draw this line. A port
 * converts `$_POST` on the way in; `json_decode($body)` already gives objects unless you ask for
 * arrays with its second argument.
 */
function goodBooking(): object
{
	return (object) [
		'organiser' => 'Alice Brennan',
		'contact_email' => 'alice@example.test',
		'venue' => (object) [
			'line1' => '12 Quay Street',
			'locality' => 'Rockhampton',
			'administrative_area' => 'QLD',
			'postal_code' => '4700',
			'country' => 'Australia',            // a name or a code; both resolve to AU
		],
		// Named rows. The key is what the failure is reported against, so a form can say which
		// attendee is wrong rather than "row 3" — and naming them is worth preferring to letting
		// PHP number them, for exactly that reason.
		'attendees' => [
			'first' => (object) ['name' => 'Bilal Haddad', 'email' => 'bilal@example.test', 'dietary' => 'No nuts'],
			'second' => (object) ['name' => 'Chen Wei', 'email' => 'chen@example.test', 'dietary' => ''],
		],
		'deposit' => (object) ['currency' => 'AUD', 'amount' => '250.00'],
		'terms_accepted' => true,
	];
}

/** The same booking, with a problem of each kind. */
function badBooking(): object
{
	$booking = goodBooking();

	$booking->contact_email = 'not-an-address';
	$booking->venue->line1 = 'PO Box 42';                        // not somewhere you can go
	$booking->attendees = [
		'first' => (object) ['name' => 'Bilal Haddad', 'email' => 'bilal@example.test', 'dietary' => ''],
		'second' => (object) ['name' => 'Bilal Haddad', 'email' => 'bilal@example.test', 'dietary' => ''], // the same person
		'third' => (object) ['name' => 'X', 'email' => 'nope', 'dietary' => ''],                           // and a bad row
	];
	$booking->deposit->amount = '12.50';                         // under the minimum
	$booking->terms_accepted = false;

	return $booking;
}

// ── running it ────────────────────────────────────────────────────────────────────────────

$schema = workshopSchema();
$wantsFailure = in_array('--bad', $argv, true);
$result = $schema->validate($wantsFailure ? badBooking() : goodBooking());

echo $wantsFailure ? "A booking with problems in it:\n\n" : "A booking that should pass:\n\n";

if (!$result->anyFailed()) {
	echo "  Everything checks out.\n\n";

	// Reading values back. `value` is what the field actually validated — the value object
	// where it has one — and it is readable whatever the verdict, so no ceremony is needed.
	$venue = $result->forField('venue')->value;
	$deposit = $result->forField('deposit')->value;
	$attendees = $result->forField('attendees');

	printf("  Venue        %s, %s %s (%s)\n", $venue->line1, $venue->locality, $venue->postalCode, $venue->countryCode);
	printf("  Deposit      %s %s\n", $deposit->currency, $deposit->amount);
	printf("  Attendees    %d\n", count($attendees->items));

	foreach ($attendees->items as $key => $item) {
		printf("    %-8s %-14s %s\n", $key, $item->forField('name')->value, $item->forField('email')->value->address());
	}

	echo "\n";

	exit(0);
}

// A failure names the constraint that failed and, for a structured value, the part it was about.
// No string-splitting: `venue` reports `line1Visitable` with part `line1`, never
// `venue.line1.visitable`.
foreach ($result->getFailed() as $fieldResult) {
	printf("  %s\n", (string) $fieldResult->field->name);

	if ($fieldResult instanceof Field\Collection\Result) {
		reportCollection($fieldResult);

		continue;
	}

	// The shape is asked separately, because it is not a constraint: it says whether the value
	// could be read at all, and when it could not, every constraint is skipped rather than failed.
	if ($fieldResult->shape->failed()) {
		printf("    %-18s could not be read as this kind of value
", 'shape');

		continue;
	}

	foreach ($fieldResult->getFailed() as $failure) {
		printf("    %-18s %s\n", $failure->name, $failure->part === null ? '' : "(part: {$failure->part})");
	}
}

echo "\n";

exit(1);

/**
 * A collection answers two different questions, so it reports both: what is wrong with the *list*
 * — too few, too many, the same row twice — and what is wrong with each row. Flattening them was
 * what made a failure unattributable before.
 */
function reportCollection(Field\Collection\Result $collection): void
{
	// The list's own verdicts. Asking the field for its constraint names beats hardcoding them,
	// and $constraints is a property because it reads state rather than asking a question.
	foreach ($collection->field->constraints->names as $name) {
		$verdict = $collection->forConstraint($name);

		if ($verdict !== null && $verdict->failed()) {
			printf("    %-18s (the list itself)\n", $name);
		}
	}

	// Then each row, under the key it was submitted with, so a form can mark the right one. A
	// named row says "second", which is worth more to somebody reading the error than "row 2".
	foreach ($collection->items as $key => $item) {
		foreach ($item->getFailed() as $fieldResult) {
			if ($fieldResult->shape->failed()) {
				printf("    row %s: %-11s could not be read\n", $key, (string) $fieldResult->field->name);

				continue;
			}

			foreach ($fieldResult->getFailed() as $failure) {
				printf("    row %s: %-11s %s\n", $key, (string) $fieldResult->field->name, $failure->name);
			}
		}
	}
}
