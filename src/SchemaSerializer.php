<?php
declare(strict_types=1);

namespace Meraki\Form;

use Meraki\Form\Schema;

interface SchemaSerializer
{
	public function serialize(Schema $schema): string;
}
