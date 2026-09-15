<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Facade;
use Meraki\Schema\FieldResult;

// Asking for input one field at a time, re-asking until it is acceptable.
//
// This is the shape of a CLI prompt, a wizard step, or a chat flow: the schema says what is
// wanted, the answer comes back, and the *field* — not the caller — decides whether it will do.
//
// Run it with a terminal attached and it prompts. Run it without one (as CI does) and it plays
// a scripted set of answers instead, so the example is still exercised rather than skipped.

$schema = new Facade('signup');

$schema->add(
	$schema->createNameField('name'),
	$schema->createEmailAddressField('email'),
	$schema->createPasswordField('password')->minLengthOf(12),
	$schema->createNumberField('age')->minValueOf(18)->maxValueOf(120),
);

// A message provider is deliberately not part of this library — turning `minLength` into a
// sentence is presentation, and it needs a locale. What the core gives you is everything the
// sentence needs: the name of what failed, and the bound it missed.
$explain = static function (FieldResult $field): string {
	if ($field->shape->wasMissing()) {
		return 'this one is needed';
	}

	if ($field->shape->wasUnreadable()) {
		// `EmailAddress` -> `email address`. Crude, and the point of it being crude here is that
		// the real thing belongs in a locale-aware message provider, not in the core.
		$kind = strtolower(trim(preg_replace('/(?<!^)[A-Z]/', ' $0', (new ReflectionClass($field->field))->getShortName())));

		return 'that is not ' . (str_contains('aeiou', $kind[0]) ? 'an ' : 'a ') . $kind;
	}

	$failed = $field->getFailed()->getFirst();

	return $failed === null
		? 'that will not do'
		: sprintf('failed "%s"%s', $failed->name, $failed->bound === null ? '' : ' (' . json_encode($failed->bound) . ')');
};

// Scripted answers for an unattended run: the first is wrong, the second is right.
$scripted = [
	'name' => ['', 'Kim Miller'],
	'email' => ['kim@', 'kim@example.test'],
	'password' => ['hunter2', 'correct horse battery staple'],
	'age' => ['twelve', '34'],
];

$interactive = stream_isatty(STDIN);
$answers = [];

foreach ($schema->fields as $field) {
	$name = (string) $field->name;

	while (true) {
		if ($interactive) {
			echo $name . ': ';
			$answer = rtrim((string) fgets(STDIN), "\r\n");
		} else {
			$answer = array_shift($scripted[$name]);
			echo $name . ': ' . ($answer === '' ? '(nothing)' : $answer) . PHP_EOL;
		}

		// One field at a time. A field validates on its own, with no schema involved — which is
		// what makes asking one question at a time possible at all.
		$result = $field->validate($answer === '' ? null : $answer);

		if (!$result->anyFailed() && !$result->shape->failed()) {
			$answers[$name] = $answer;

			break;
		}

		echo '  ' . $explain($result) . ', try again' . PHP_EOL;
	}
}

echo PHP_EOL . 'Everything answered. Validating the whole thing at once:' . PHP_EOL;

// Re-validating the assembled answers is not redundant: a rule can make one field depend on
// another, and no single-field check can see that.
$result = $schema->validate((object) $answers);

echo '  any failures: ' . var_export($result->anyFailed(), true) . PHP_EOL;

foreach ($result as $field) {
	printf("  %-10s %-8s %s" . PHP_EOL, (string) $field->field->name, $field->status->name, $field->source->name);
}
