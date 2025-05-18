<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Time;

use Meraki\Schema\Field\Modifier\TimePrecision;
use Brick\DateTime\LocalTime;

interface PrecisionCaster
{
	public function cast(mixed $value, TimePrecision $precision): LocalTime;
}
