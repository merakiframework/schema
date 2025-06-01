<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\DateTime;

use Meraki\Schema\Field\DateTime\TimePrecision;
use Brick\DateTime\LocalDateTime;

interface PrecisionCaster
{
	public function cast(mixed $value, TimePrecision $precision): LocalDateTime;
}
