<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\DateTime;

use Brick\DateTime\LocalDateTime;

enum TimePrecision: string
{
	case Minutes = 'minutes';
	case Seconds = 'seconds';
	case Nanoseconds = 'nanoseconds';

	/**
	 * Step forward by one precision unit (e.g., one minute, one second).
	 *
	 * @template T of LocalDateTime
	 * @param T $time
	 * @return T
	 */
	public function stepForwardByPrecisionUnit(LocalDateTime $time): LocalDateTime
	{
		return match ($this) {
			self::Minutes => $time->plusMinutes(1),
			self::Seconds => $time->plusSeconds(1),
			default => $time->plusNanos(1),
		};
	}

	/**
	 * Step backward by one precision unit (e.g., one minute, one second).
	 *
	 * @template T of LocalDateTime
	 * @param T $time
	 * @return T
	 */
	public function stepBackwardByPrecisionUnit(LocalDateTime $time): LocalDateTime
	{
		return match ($this) {
			self::Minutes => $time->minusMinutes(1),
			self::Seconds => $time->minusSeconds(1),
			default => $time->minusNanos(1),
		};
	}
}
