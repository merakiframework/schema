<?php

require_once __DIR__ . '/../vendor/autoload.php';

$schema = new Meraki\Form\Schema();

$schema->add(
	(new Meraki\Form\Field('username', 'text'))
		->require()
		->minLengthOf(3)
		->maxLengthOf(20)
);

$schema->add(
	(new Meraki\Form\Field('age', 'number'))
		->require()
		->minOf(18)
		->maxOf(120)
);

echo '<pre>';
echo $schema->serialize(new Meraki\Form\Serializer\Json());
echo '</pre>';
