<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\DateTime;

use Meraki\Schema\Field\DateTime\PrecisionCaster;
use Meraki\Schema\Field\DateTime\TimePrecision;
use Brick\DateTime\LocalDateTime;
use InvalidArgumentException;

final class PreservePrecision implements PrecisionCaster
{
	public function cast(mixed $value, TimePrecision $precision): LocalDateTime
	{
		$dateTime = LocalDateTime::parse($value);
		$hasSeconds = $dateTime->getSecond() !== 0;
		$hasNanoseconds = $dateTime->getNano() !== 0;

		if ($precision === TimePrecision::Minutes && ($hasSeconds || $hasNanoseconds)) {
			throw new InvalidArgumentException('Value can only have a precision in minutes.');
		}

		if ($precision === TimePrecision::Seconds && $hasNanoseconds) {
			throw new InvalidArgumentException('Value can only have a precision in seconds.');
		}

		return $dateTime;
	}
}
