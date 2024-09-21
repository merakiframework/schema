<?php

require_once __DIR__ . '/../vendor/autoload.php';

$serializedSchema = <<<JSON
	{
		"fields": [
			{
				"name": "username",
				"type": "text",
				"constraints": {
					"required": true,
					"min": 3,
					"max": 20
				}
			},
			{
				"name": "age",
				"type": "number",
				"constraints": {
					"required": true,
					"min": 18,
					"max": 120
				}
			}
		]
	}
JSON;

$deserializer = new Meraki\Form\Deserializer\Json(
	new Meraki\Form\Field\Factory(),
	Meraki\Form\Constraint\Factory::useBundled(),
);

$schema = $deserializer->deserialize($serializedSchema);

var_dump($schema->fields->first());
