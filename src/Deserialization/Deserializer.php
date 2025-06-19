<?php
declare(strict_types=1);

namespace Meraki\Schema\Deserialization;

use Meraki\Schema\Facade;

interface Deserializer
{
	public function deserialize(string $serializedSchema): Facade;
}
