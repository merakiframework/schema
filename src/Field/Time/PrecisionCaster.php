<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Time;

use Meraki\Schema\Field\Time\Precision;
use Brick\DateTime\LocalTime;

interface PrecisionCaster
{
	public function cast(mixed $value, Precision $precision): LocalTime;
}
