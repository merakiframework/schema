<?php
declare(strict_types=1);

namespace Meraki\Form;

use Meraki\Form\Schema;

interface SchemaDeserializer
{
	public function deserialize(string $serializedSchema): Schema;
}
