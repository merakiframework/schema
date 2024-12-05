<?php

require_once __DIR__ . '/../vendor/autoload.php';

$schema = new Meraki\Schema\SchemaFacade('contact_form');

$schema->addTextField('username')
	->require()
	->matches('/^[a-zA-Z0-9_]+$/')
	->minLengthOf(3);

$schema->addNumberField('age')
	->require()
	->minOf(18)
	->maxOf(120);

$validUserData = [
	'username' => 'johndoe',
	'age' => 25,
];

$invalidUserData = [
	'username' => '',
	'age' => 15,
];

$userData = isset($_GET['pass']) ? $validUserData : $invalidUserData;
$result = $schema->validate($userData);

echo '<pre>';
if ($result->allPassed()) {
	echo 'The data is valid.';
} else {
	foreach ($result->getFailures() as $fieldFailures) {
		if ($fieldFailures->valueValidationResult->passed()) {
			echo "Field '{$fieldFailures->field->name}' passed." . PHP_EOL;
			echo "Validating constraints..." . PHP_EOL;

			foreach ($fieldFailures->constraintValidationResults as $constraintFailure) {
				echo "'{$constraintFailure->constraint->name}' constraint failed." . PHP_EOL;
			}
		} else {
			echo "Field '{$fieldFailures->field->name}' failed." . PHP_EOL;
			echo "Skipping constraint validation..." . PHP_EOL;
		}

		echo PHP_EOL;
	}
}
echo '</pre>';
