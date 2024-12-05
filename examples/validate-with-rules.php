<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Rule;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\Outcome;

echo '<pre>';
$schema = new Meraki\Schema\SchemaFacade('editing_one');

$schema->addUuidField('id')
	->accept(7);

$schema->addNameField('full_name')
	->minLengthOf(1)
	->maxLengthOf(255);

$schema->addTextField('licence_number')
	->minLengthOf(1)
	->maxLengthOf(255);

$schema->addBooleanField('licence_verified')
	->makeOptional()
	->prefill(false);

$schema->addBooleanField('has_log_book')
	->makeOptional()
	->prefill(true);

$schema->addDurationField('log_book_time_completed')
	->makeOptional()
	->minOf('PT0M')
	->maxOf('PT200H');

$schema->addBooleanField('log_book_closed')
	->makeOptional()
	->prefill(false);

$schema->addEnumField('instructor', ['any', '017F22E2-79B0-7CC3-98C4-DC0C0C07398F'])
	->prefill('any');

$schema->addAddressField('pickup_location');

$schema->addBooleanField('use_own_vehicle')
	->makeOptional()
	->prefill(false);

$schema->addEnumField('transmission_type', ['automatic', 'manual', 'synchro', 'non-synchro']);

$schema->addRule(
	Rule::matchAll()
		->when(Condition::create('#/fields/has_log_book/value', 'equals', true))
		->then(Outcome::require('#/fields/log_book_time_completed'))
);

$results = $schema->validate([]);

foreach ($results as $result) {
	/** @var Meraki\Schema\Attribute\Value */
	$value = $result->field->attributes->findByName('value')
		->defaultsTo($result->field->attributes->findByName('default_value'));
	echo $result->field->name . ' = ' . var_export($value->value, true) . ' : ' . match (true) {
		$result->passed() => 'Passed',
		$result->failed() => 'Failed',
		$result->skipped() => 'Skipped',
		default => 'Unknown',
	} . PHP_EOL;
}
echo '</pre>';
