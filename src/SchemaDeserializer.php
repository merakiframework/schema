<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\SchemaFacade;

interface SchemaDeserializer
{
	public function deserialize(string $serializedSchema): SchemaFacade;
}
