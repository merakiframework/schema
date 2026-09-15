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
	 * Whether the time carries nothing finer than this precision describes.
	 *
	 * `12:34` is covered by minute precision; `12:34:56` is not. Used to report a value that
	 * is more precise than the field accepts, which is a constraint failure rather than a
	 * parse error — the value is a perfectly good time, just not one this field can hold.
	 */
	public function covers(LocalTime $time): bool
	{
		return match ($this) {
			self::Minutes => $time->getSecond() === 0 && $time->getNano() === 0,
			self::Seconds => $time->getNano() === 0,
			self::Nanoseconds => true,
		};
	}

	/**
	 * Discards anything this precision does not cover, so minute precision turns `12:34:56`
	 * into `12:34`. Total: there is nothing it can be given that it cannot answer.
	 */
	public function truncate(LocalTime $time): LocalTime
	{
		return match ($this) {
			self::Minutes => $time->withSecond(0)->withNano(0),
			self::Seconds => $time->withNano(0),
			self::Nanoseconds => $time,
		};
	}

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
