<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\SchemaFacade;

interface SchemaSerializer
{
	public function serialize(SchemaFacade $schema): string;
}
