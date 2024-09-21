<?php

require_once __DIR__ . '/../vendor/autoload.php';

$schema = new Meraki\Form\Schema();

$schema->add(
	(new Meraki\Form\Field('username', 'text'))
		->require()
		->minLengthOf(3)
		->maxLengthOf(20)
);

$schema->add(
	(new Meraki\Form\Field('age', 'number'))
		->require()
		->minOf(18)
		->maxOf(120)
);

$validUserData = [
	'username' => 'johndoe',
	'age' => 25,
];

$invalidUserData = [
	'username' => 'jd',
	'age' => 15,
];

$userData = isset($_GET['pass']) ? $validUserData : $invalidUserData;
$result = $schema->validate($userData);

echo '<pre>';
if ($result->passed()) {
	echo 'The data is valid.';
} else {
	foreach ($result->errors as $fieldName => $errors) {
		echo 'The data is invalid for field "' . $fieldName . '":' . PHP_EOL;

		foreach ($errors as $error) {
			echo '    - ' . $error . PHP_EOL;
		}

		echo PHP_EOL;
	}
}
echo '</pre>';
