<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Time;

use Brick\DateTime\LocalTime;

enum Precision: string
{
	case Minutes = 'minutes';
	case Seconds = 'seconds';
	case Nanoseconds = 'nanoseconds';

	/**
	 * Step forward by one precision unit (e.g., one minute, one second).
	 */
	public function stepForwardByPrecisionUnit(LocalTime $time): LocalTime
	{
		return match ($this) {
			self::Minutes => $time->plusMinutes(1),
			self::Seconds => $time->plusSeconds(1),
			default => $time->plusNanos(1),
		};
	}

	/**
	 * Step backward by one precision unit (e.g., one minute, one second).
	 */
	public function stepBackwardByPrecisionUnit(LocalTime $time): LocalTime
	{
		return match ($this) {
			self::Minutes => $time->minusMinutes(1),
			self::Seconds => $time->minusSeconds(1),
			default => $time->minusNanos(1),
		};
	}
}
