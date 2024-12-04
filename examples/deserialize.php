<?php

require_once __DIR__ . '/../vendor/autoload.php';

$schema = Meraki\Schema\SchemaFacade::deserialize(__DIR__ . '/schema.json');

echo '<pre>';
var_dump($schema);
echo '</pre>';
