<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Time;

use Meraki\Schema\Field\Time\Precision;
use Meraki\Schema\Field\Time\PrecisionCaster;
use Brick\DateTime\LocalTime;
use InvalidArgumentException;

final class PreservePrecision implements PrecisionCaster
{
	public function cast(mixed $value, Precision $precision): LocalTime
	{
		$dateTime = LocalTime::parse($value);
		$hasSeconds = $dateTime->getSecond() !== 0;
		$hasNanoseconds = $dateTime->getNano() !== 0;

		if ($precision === Precision::Minutes && ($hasSeconds || $hasNanoseconds)) {
			throw new InvalidArgumentException('Value can only have a precision in minutes.');
		}

		if ($precision === Precision::Seconds && $hasNanoseconds) {
			throw new InvalidArgumentException('Value can only have a precision in seconds.');
		}

		return $dateTime;
	}
}
