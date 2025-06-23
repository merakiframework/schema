<?php

require_once __DIR__ . '/../vendor/autoload.php';

echo '<pre>';

$schema = Meraki\Schema\Facade::deserialize(__DIR__ . '/schema.json');

var_dump($schema->traverse(new Meraki\Schema\Scope('#/fields/email_address/value')));

echo '</pre>';
