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
	 * Whether the date-time carries nothing finer than this precision describes.
	 *
	 * `2026-01-01T12:34` is covered by minute precision; `2026-01-01T12:34:56` is not. Used to
	 * report a value that is more precise than the field accepts, which is a constraint failure
	 * rather than a parse error — the value is a perfectly good date-time, just not one this
	 * field can hold.
	 */
	public function covers(LocalDateTime $dateTime): bool
	{
		return match ($this) {
			self::Minutes => $dateTime->getSecond() === 0 && $dateTime->getNano() === 0,
			self::Seconds => $dateTime->getNano() === 0,
			self::Nanoseconds => true,
		};
	}

	/**
	 * Discards anything this precision does not cover, so minute precision turns
	 * `2026-01-01T12:34:56` into `2026-01-01T12:34`. Total: there is nothing it can be given
	 * that it cannot answer.
	 */
	public function truncate(LocalDateTime $dateTime): LocalDateTime
	{
		return match ($this) {
			self::Minutes => $dateTime->withSecond(0)->withNano(0),
			self::Seconds => $dateTime->withNano(0),
			self::Nanoseconds => $dateTime,
		};
	}

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
