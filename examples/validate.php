<?php

require_once __DIR__ . '/../vendor/autoload.php';

$schema = new Meraki\Schema\SchemaFacade('contact_form');

$schema->addTextField('username')
	->addValidator(new Meraki\Schema\Validator\MatchesRegex('/^[a-zA-Z0-9_]+$/'))
	->addValidator(new Meraki\Schema\Validator\HasMinCharCountOf(3));

$schema->addNumberField('age')
	->addValidator(new Meraki\Schema\Validator\HasMinValueOf(18))
	->addValidator(new Meraki\Schema\Validator\HasMaxValueOf(120));

$validUserData = [
	'username' => 'johndoe',
	'age' => 25,
];

$invalidUserData = [
	'username' => '',
	'age' => 15,
];

$userData = isset($_GET['pass']) ? $validUserData : $invalidUserData;
$schemaResult = $schema->validate($userData);

echo '<pre>';
if ($schemaResult->allPassed()) {
	echo 'The data is valid.';
} else {
	foreach ($schemaResult->getFailed() as $fieldResult) {
		foreach ($fieldResult->getFailed() as $failure) {
			echo '"' . $failure->validator->name . '" validator failed for "' . $fieldResult->field->name . '" field.' . PHP_EOL;
		}

		echo PHP_EOL;
	}
}
echo '</pre>';
