<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\DateTime;

use Meraki\Schema\Field\DateTime\PrecisionCaster;
use Meraki\Schema\Field\Modifier\TimePrecision;
use Brick\DateTime\LocalDateTime;

final class TruncatePrecision implements PrecisionCaster
{
	public function cast(mixed $value, TimePrecision $precision): LocalDateTime
	{
		$dateTime = LocalDateTime::parse($value);
		$hasSeconds = $dateTime->getSecond() !== 0;
		$hasNanoseconds = $dateTime->getNano() !== 0;

		if ($precision === TimePrecision::Minutes && ($hasSeconds || $hasNanoseconds)) {
			return $dateTime->withSecond(0)->withNano(0);
		}

		if ($precision === TimePrecision::Seconds && $hasNanoseconds) {
			return $dateTime->withNano(0);
		}

		return $dateTime;
	}
}
