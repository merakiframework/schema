<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Field\Text;
use Meraki\Schema\FieldName;

// A field can be built and validated on its own, without a Facade.
//
// Note that every configuration method returns a *new* field rather than changing this one:
// a field is immutable, so the result has to be kept. Writing
//
//     $username->minLengthOf(3);
//
// on a line of its own configures nothing at all — it builds a copy and throws it away.
$username = (new Text(new FieldName('username')))
	->mustMatch('/^[a-zA-Z0-9_]+$/')
	->minLengthOf(3);

// validate() is a pure query: the value goes in as an argument and the result comes back, so
// nothing is stored on the field and the same field can serve two requests at once.
$result = $username->validate('ab');	// too short -> the "minLength" constraint fails

echo 'Field status: ' . $result->status->name . PHP_EOL;

// Two separate questions, and the order matters.
//
// The *shape* asks whether there was a usable value at all — could this be read as text? Every
// constraint is a narrowing of a value that is already the right shape, so if the shape fails
// there is nothing for a constraint to have an opinion about, and they are all skipped rather
// than piling more failures on top of the one real problem.
echo 'Shape:        ' . $result->shape->name . PHP_EOL;

foreach ($result->constraintNames as $name) {
	$constraint = $result->forConstraint($name);

	printf("  %-10s %-8s%s" . PHP_EOL, $name, $constraint->status->name,
		// The bound is what a message needs in order to say something useful: not
		// "too short" but "needs at least 3 characters".
		$constraint->bound === null ? '' : ' (bound: ' . json_encode($constraint->bound) . ')');
}

echo PHP_EOL . 'And with input it accepts:' . PHP_EOL;

$ok = $username->validate('johndoe');

echo 'Field status: ' . $ok->status->name . PHP_EOL;
echo 'Value:        ' . var_export($ok->value, true) . PHP_EOL;

// What was submitted is kept beside what the field made of it, so a form can echo back exactly
// what the person typed while the application works with the parsed value.
echo 'Given:        ' . var_export($ok->given, true) . PHP_EOL;
