<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Field;
use Meraki\Schema\Rule;
use Meraki\Schema\Rule\Builder;
use Meraki\Schema\Rule\Outcome;
use Meraki\Schema\Rule\Condition;

$schema = new Meraki\Schema\Facade('contact_us');
$schema->addNameField('name');
$schema->addEnumField(
	'contact_method',
	['email', 'phone'],
	fn(Field\Enum $contactMethod): Field\Enum => $contactMethod->prefill('email')
);
$schema->addEmailAddressField('email_address');
$schema->addPhoneNumberField('phone_number');
$schema->addTextField(
	'message',
	fn(Field\Text $message): Field\Text => $message->minLengthOf(10)->maxLengthOf(500)
);

// manipulating rules using the fluent interface
$schema->whenAllMatch(fn(Builder $r): Builder =>
	$r->whenEquals('#/fields/contact_method/value', 'email')
		->thenRequire('#/fields/email_address')
		->thenMakeOptional('#/fields/phone_number')
);

// manipulating rules directly
$schema->addRule(
	new Rule(
		new Condition\AllOf(
			new Condition\Equals('#/fields/contact_method/value', 'phone')
		),
		[
			new Outcome\_Require('#/fields/phone_number'),
			new Outcome\MakeOptional('#/fields/email_address'),
		]
	)
);

echo '<pre>';
echo $schema->serialize();
echo '</pre>';
