<?php

require_once __DIR__ . '/../vendor/autoload.php';

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

// $schema->addRule(
// 	new Rule(
// 		Condition::matchAll(
// 			Condition::create('#/fields/has_log_book/value', 'equals', true),
// 		),
// 		[
// 			Outcome::require('#/fields/log_book_time_completed'),
// 		]
// 	)
// );

$renderer = new Meraki\Schema\HtmlRenderer(
	[
		'instructor' => [
			// defining UI options for each enum option is
			// a little verbose, but allows for more control
			'options' => [
				'any' => [
					'label' => 'Any'
				],
				'017F22E2-79B0-7CC3-98C4-DC0C0C07398F' => [
					'label' => 'Russell Dann'
				]
			]
		]
	]
);

echo '<html><head><title>Schema Form</title></head><body>';
echo $renderer->render($schema);
echo '</body></html>';
