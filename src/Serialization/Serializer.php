<?php
declare(strict_types=1);

namespace Meraki\Schema\Serialization;

use Meraki\Schema\Facade;

interface Serializer
{
	public function serialize(Facade $schema): string;
}
