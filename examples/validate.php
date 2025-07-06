<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Field\Text;
use Meraki\Schema\Field\Number;

$schema = new Meraki\Schema\Facade('contact_form');

$schema->addTextField('username')
	->matches('/^[a-zA-Z0-9_]+$/')
	->minLengthOf(3)
	->maxLengthOf(20);

$schema->addNumberField('age')
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
$schemaResult = $schema->validate($userData);

echo '<pre>';
if ($schemaResult->passed()) {
	echo 'The data is valid.';
} else {
	foreach ($schemaResult->getFailed() as $fieldResult) {
		foreach ($fieldResult->getFailed() as $failureResult) {
			echo '"' . $failureResult->name . '" property failed validation for "' . $fieldResult->field->name . '" field.' . PHP_EOL;
		}

		echo PHP_EOL;
	}
}
echo '</pre>';
