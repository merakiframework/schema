<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Facade;
use Meraki\Schema\Field\ShapeValidationResult;

$schema = new Facade('contact_form');

// Fields are built by the factory, configured, then added. Configuring returns a new field each
// time, so the finished one is what gets added — nothing can be changed after it is in a schema.
$schema->add($schema->createTextField('username')
	->mustMatch('/^[a-zA-Z0-9_]+$/')
	->minLengthOf(3)
	->maxLengthOf(20));

$schema->add($schema->createNumberField('age')
	->minValueOf(18)
	->maxValueOf(120));

// A payload is a *record* of field names, so it is an object. An array would mean a list — see
// the note on Field\Definition::recordIn(). Converting is the port's job; `json_decode($body)`
// already gives objects unless its second argument asks for arrays.
$valid = (object) [
	'username' => 'johndoe',
	'age' => 25,
];

$invalid = (object) [
	'username' => '',		// too short, and does not match the pattern
	'age' => 15,			// below the minimum
];

// Pass --pass to see the accepted data instead.
$data = in_array('--pass', $argv ?? [], true) ? $valid : $invalid;
$result = $schema->validate($data);

if (!$result->anyFailed()) {
	echo 'The data is valid.' . PHP_EOL;

	return;
}

foreach ($result->getFailed() as $field) {
	echo $field->field->name . PHP_EOL;

	foreach ($field->getFailed() as $failure) {
		// Two kinds of failure, and they are different questions.
		//
		// A shape failure means the value could not be read as this kind of thing at all, so
		// there is no constraint to blame and nothing to report a bound for. A constraint
		// failure means the value was read fine and then broke a rule — which is the case that
		// can say something specific, because it knows which rule and what the limit was.
		echo $failure instanceof ShapeValidationResult
			? '  is not usable as this kind of value' . PHP_EOL
			: sprintf('  %s (limit: %s)' . PHP_EOL, $failure->name, json_encode($failure->bound));
	}

	echo PHP_EOL;
}
