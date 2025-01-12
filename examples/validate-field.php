<?php

require_once __DIR__ . '/../vendor/autoload.php';

$factory = new Meraki\Schema\Field\Factory();
$textField = $factory->createText('username');

$textField->makeOptional()
	// ->deferValidation()
	->matches('/^[a-zA-Z0-9_]+$/')
	->minLengthOf(3)
	->input(123)
	;

if ($textField->validationResult->passed()) {
	echo 'All constraints have passed validation.' . PHP_EOL;
}

if ($textField->validationResult->skipped()) {
	echo 'The following constraints have skipped validation: ' . PHP_EOL;
	foreach ($textField->validationResult->getSkipped() as $skipped) {
		echo $skipped->constraint::class . PHP_EOL;
	}
}

if ($textField->validationResult->pending()) {
	echo 'Constraint validation is pending.' . PHP_EOL;
}

if ($textField->validationResult->failed()) {
	echo 'The following constraints have failed validation: ' . PHP_EOL;
	foreach ($textField->validationResult->getFailures() as $failure) {
		echo $failure->constraint::class . PHP_EOL;
	}
	echo 'The following constraints have skipped validation: ' . PHP_EOL;
	foreach ($textField->validationResult->getSkipped() as $skipped) {
		echo $skipped->constraint::class . PHP_EOL;
	}
}
