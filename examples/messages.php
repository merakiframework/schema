<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Facade;
use Meraki\Schema\Message\Mf2\Mf2Provider;
use Meraki\Schema\Message\PartedSet;

// Messages are optional, installed rather than written, and never able to change a verdict.
//
// In a real application the pack is a Composer package —
// `Mf2Provider::fromPackage('meraki/schema-language-english')` — containing nothing but `.mfr`
// files, so a Rust or JavaScript implementation of this library renders the same sentences. This
// example writes two small ones to a temporary directory so it runs from a fresh clone.

$pack = sys_get_temp_dir() . '/meraki-example-lang';

@mkdir($pack);

file_put_contents($pack . '/en.mfr', <<<'MFR'
	@locale = en

	shape.missing = This {$kind} is required.
	shape.unreadable = That is not a valid {$kind}.

	kind.Address = address
	kind.EmailAddress = email address
	kind.Password = password

	part.postal_code = postal code
	part.administrative_area = state or region

	minLength = Use at least {$bound} characters.
	postalCodeFormat = That is not a valid {$part} for the country you chose.
	administrativeArea = That is not a {$part} we recognise for the country you chose.

	# The ladder: a more specific key wins, so "at least 12 characters" can become better advice
	# for a password without changing what every other field says.
	Password.minLength = Use at least {$bound} characters. A phrase of a few words is easier to remember and harder to guess.
	MFR);

// A variant holds only what differs. Nothing here restates `postalCodeFormat` — that message says
// "{$part}", and redefining the part is enough.
file_put_contents($pack . '/en_AU.mfr', <<<'MFR'
	@locale = en_AU

	part.postal_code = postcode
	part.administrative_area = state
	MFR);

// The provider is a source, registered once, holding every language it can serve — like the clock.
$schema = new Facade('signup', messages: Mf2Provider::fromDirectory($pack));

$schema->add(
	$schema->createEmailAddressField('email'),
	$schema->createPasswordField('secret')->minLengthOf(12),
	$schema->createAddressField('billing', ['AU']),
);

$submitted = (object) [
	'email' => 'not-an-email',
	'secret' => 'hunter2',
	'billing' => (object) [
		'line1' => '12 Denham Street',
		'locality' => 'Rockhampton',
		'administrative_area' => 'ZZ',
		'postal_code' => '99',
		'country' => 'AU',
	],
];

// The *language* is part of the request, because that is the part that varies. One schema serves
// every reader.
foreach (['en', 'en-AU', 'de-AT'] as $locale) {
	printf('%s%s:%s', PHP_EOL, $locale, PHP_EOL);

	foreach ($schema->validate($submitted, locale: $locale) as $field) {
		if (!$field->anyFailed()) {
			continue;
		}

		$messages = $field->messages;

		if ($messages->isEmpty()) {
			printf('  %-10s (no wording for this language)%s', $field->field->name, PHP_EOL);
			continue;
		}

		// Two shapes, and the *field* decides which — not what happened to fail. A field holding
		// one value gives a flat list; one whose value has named parts groups by part, so a
		// renderer can put each sentence beside the input it belongs to.
		if ($messages instanceof PartedSet) {
			// `whole` is the half people forget: an email address has parts, but "that is not a
			// valid email address" is about the address itself and belongs to none of them.
			foreach ($messages->whole as $said) {
				printf('  %-10s %-20s %s%s', $field->field->name, '(whole)', $said, PHP_EOL);
			}

			foreach ($messages->parts as $part) {
				printf('  %-10s %-20s %s%s', $field->field->name, $part, $messages->forPart($part)->first, PHP_EOL);
			}

			continue;
		}

		printf('  %-10s %-20s %s%s', $field->field->name, '', $messages->first, PHP_EOL);
	}
}

// `de-AT` printed every failure it would otherwise have printed, with nothing to say about them.
// That is the property the whole design rests on: validation is language-independent, so a missing
// translation can never change an outcome.
printf(
	'%sSame verdicts in every language: %s%s',
	PHP_EOL,
	$schema->validate($submitted, locale: 'en')->status === $schema->validate($submitted, locale: 'de-AT')->status
		? 'yes'
		: 'no',
	PHP_EOL,
);

// A field validated on its own has no schema, so no provider, so no messages. Stated rather than
// worked around: a definition that knew about languages could not be serialised the same way twice.
printf(
	'A field on its own has messages: %s%s',
	$schema->fields->getByName('secret')->validate('hunter2')->messages->isEmpty() ? 'no' : 'yes',
	PHP_EOL,
);

array_map(unlink(...), glob($pack . '/*.mfr') ?: []);
@rmdir($pack);
