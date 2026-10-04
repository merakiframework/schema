<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Definition;
use Meraki\Schema\Message\Mf2\Mf2Provider;

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
	part.subdivision = state or region

	minLength = Use at least {$bound} characters.
	postalCodeFormat = That is not a valid {$part} for the country you chose.
	knownSubdivision = That is not a {$part} we recognise for the country you chose.

	# The ladder: a more specific key wins, so "at least 12 characters" can become better advice
	# for a password without changing what every other field says.
	Password.minLength = Use at least {$bound} characters. A phrase of a few words is easier to remember and harder to guess.
	MFR);

// A variant holds only what differs. Nothing here restates `postalCodeFormat` — that message says
// "{$part}", and redefining the part is enough.
file_put_contents($pack . '/en_AU.mfr', <<<'MFR'
	@locale = en_AU

	part.postal_code = postcode
	part.subdivision = state
	MFR);

// A source: built once, holding every language it can serve, safe to share. It is handed to
// validate() rather than to the schema, because wording is not a fact about a definition.
$wording = Mf2Provider::fromDirectory($pack);

$schema = new Definition('signup');

$schema->add(
	$schema->createEmailAddressField('email'),
	$schema->createPasswordField('secret')->minLengthOf(12),
	$schema->createAddressField('billing', ['AU']),
);

$submitted = (object) [
	'email' => 'not-an-email',
	'secret' => 'hunter2',
	'billing' => (object) [
		'street' => ['12 Denham Street'],
		'locality' => 'Rockhampton',
		'subdivision' => 'ZZ',
		'postal_code' => '99',
		'country' => 'AU',
	],
];

// The *language* is part of the request, because that is the part that varies. One schema serves
// every reader.
foreach (['en', 'en-AU', 'de-AT'] as $locale) {
	printf('%s%s:%s', PHP_EOL, $locale, PHP_EOL);

	foreach ($schema->validate($submitted, locale: $locale, messages: $wording) as $field) {
		if (!$field->anyFailed()) {
			continue;
		}

		// Every failure is a violation: its code, the part it concerns, the bound that applied,
		// and — when the pack had wording — its sentence. They read the value as a whole first,
		// then part by part in the order the value declares them, so a renderer can put each
		// sentence beside the input it belongs to.
		foreach ($field->violations as $violation) {
			printf(
				'  %-10s %-14s %s%s',
				$field->field->name,
				$violation->part === null ? '' : $violation->part->value,
				$violation->message ?? "({$violation->name}: no wording in this language)",
				PHP_EOL,
			);
		}
	}
}

// `de-AT` printed every failure it would otherwise have printed, with nothing to say about them.
// That is the property the whole design rests on: validation is language-independent, so a missing
// translation can never change an outcome.
printf(
	'%sSame verdicts in every language: %s%s',
	PHP_EOL,
	$schema->validate($submitted, locale: 'en', messages: $wording)->status
		=== $schema->validate($submitted, locale: 'de-AT', messages: $wording)->status
		? 'yes'
		: 'no',
	PHP_EOL,
);

// A field validated on its own has no schema, so no provider, so its violations carry codes and no
// sentences. Stated rather than worked around: a definition that knew about languages could not be
// serialised the same way twice.
$alone = $schema->fields->getByName('secret')->validate('hunter2')->violations;

printf(
	'A field on its own: %d violation, %d sentences%s',
	count($alone),
	count($alone->messages),
	PHP_EOL,
);

array_map(unlink(...), glob($pack . '/*.mfr') ?: []);
@rmdir($pack);
