<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Time;

use Meraki\Schema\Field\Time\Precision;
use Meraki\Schema\Field\Time\PrecisionCaster;
use Brick\DateTime\LocalTime;

final class TruncatePrecision implements PrecisionCaster
{
	public function cast(mixed $value, Precision $precision): LocalTime
	{
		$dateTime = LocalTime::parse($value);
		$hasSeconds = $dateTime->getSecond() !== 0;
		$hasNanoseconds = $dateTime->getNano() !== 0;

		if ($precision === Precision::Minutes && ($hasSeconds || $hasNanoseconds)) {
			return $dateTime->withSecond(0)->withNano(0);
		}

		if ($precision === Precision::Seconds && $hasNanoseconds) {
			return $dateTime->withNano(0);
		}

		return $dateTime;
	}
}
