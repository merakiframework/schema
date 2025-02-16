<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Attribute\Value;

interface FieldSanitizer
{
	public function sanitize(Value $value): Value;
}
