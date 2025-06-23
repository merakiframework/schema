<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Field;
use Meraki\Schema\Rule;
use Meraki\Schema\Rule\Builder;
use Meraki\Schema\Rule\Outcome;
use Meraki\Schema\Rule\Condition;

$schema = new Meraki\Schema\Facade('contact_us');
$schema->addNameField('name');
$schema->addEnumField('contact_method', ['email', 'phone']);
$schema->addEmailAddressField('email_address');
$schema->addPhoneNumberField('phone_number');
$schema->addTextField('message', fn(Field\Text $field): Field\Text => $field->minLengthOf(10)->maxLengthOf(500));

// manipulating rules using the fluent interface
$schema->whenAllMatch(fn(Builder $r): Builder =>
	$r->whenEquals('#/fields/contact_method/value', 'email')
		->thenMakeOptional('#/fields/phone_number')
);

// manipulating rules directly
$schema->addRule(
	new Rule(
		new Condition\AllOf(
			new Condition\Equals('#/fields/contact_method/value', 'phone')
		),
		[
			new Outcome\MakeOptional('#/fields/email_address'),
		]
	)
);

echo '<pre>';
echo $schema->serialize();
echo '</pre>';
