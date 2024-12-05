<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Schema\Rule;
use Meraki\Schema\Rule\Printer;
use Meraki\Schema\Rule\Condition;
use Meraki\Schema\Rule\Outcome;

$rule = Rule::matchAll()
	->when(Condition::create('#/fields/contact_method/value', 'equals', 'phone'))
	->then(Outcome::require('#/fields/phone_number'));

$prettyFormatted = (new Printer())->print($rule);
echo '<pre>', $prettyFormatted, '</pre>';
