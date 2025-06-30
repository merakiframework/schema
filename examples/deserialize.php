<?php

require_once __DIR__ . '/../vendor/autoload.php';

echo '<pre>';

// rules are applied after deserialization
$schema = Meraki\Schema\Facade::deserialize(__DIR__ . '/schema.json');

// default value for contact_method is 'email'
// and therefore email_address field is required and phone_number is optional
var_dump($schema->traverse(new Meraki\Schema\Scope('#/fields/contact_method/value')));
var_dump($schema->traverse(new Meraki\Schema\Scope('#/fields/email_address'))->value->optional === false);
var_dump($schema->traverse(new Meraki\Schema\Scope('#/fields/phone_number'))->value->optional === true);

// provide user input to the schema
// rules will be re-applied
$schema->input([
	'name' => 'Jane Doe',
	'contact_method' => 'phone',
	'email_address' => 'jane.doe@example.com',
	'phone_number' => '+61 411 222 333',
	'message' => 'Hello, World!',
]);

// contact_method has been overridden to 'phone' by user input
// and therefore phone_number field is required and email_address is optional
var_dump($schema->traverse(new Meraki\Schema\Scope('#/fields/contact_method/value')));
var_dump($schema->traverse(new Meraki\Schema\Scope('#/fields/email_address'))->value->optional === true);
var_dump($schema->traverse(new Meraki\Schema\Scope('#/fields/phone_number'))->value->optional === false);

echo '</pre>';
