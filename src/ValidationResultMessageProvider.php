<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Field;
use Meraki\Schema\ValidatorName;

interface ValidationResultMessageProvider
{
	public function getErrorMessage(ValidatorName $name, Field $field, array $extra = []): string;
}
