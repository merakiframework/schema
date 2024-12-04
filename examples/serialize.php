<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Outcome;
use Meraki\Schema\Rule;
use Meraki\Schema\Rule\Condition;

$schema = new Meraki\Schema\SchemaFacade('contact_form');

$name = $schema->addNameField('name')
	->require();

$contactMethod = $schema->addEnumField('contact_method', ['email', 'phone'])
	->require();

$message = $schema->addTextField('message')
	->require()
	->minLengthOf(10)
	->maxLengthOf(500)
	->allowMultipleLines();

$emailAddress = $schema->addEmailAddressField('email_address');

$phoneNumber = $schema->addPhoneNumberField('phone_number');


$schema->addRule(
	new Rule(
		Condition::matchAny(
			Condition::matchAll(
				Condition::create('#/field_1/value', 'equals', 'value1'),
				Condition::create('#/field_2/value', 'equals', 'value2'),
				Condition::matchAll(
					Condition::create('#/field_3/value', 'equals', 'value3'),
					Condition::create('#/field_4/value', 'notEquals', 'value4'),
				),
			),

			Condition::matchNone(
				Condition::create('#/contact_method/value', 'equals', 'phone'),
			),
		),
		[
			Outcome::doStuff('#/a_field'),
			Outcome::doOtherStuff('#/another_field')
		]
	)
);

echo '<pre>';
echo $schema->serialize();
echo '</pre>';
