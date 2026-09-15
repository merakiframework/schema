<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\DateTime;

use Brick\DateTime\LocalDateTime;

/**
 * What happens to precision the field did not ask for.
 *
 * Replaced a `PrecisionCaster` interface and its two implementations. Once neither of them was
 * allowed to throw, the only difference left between them was one line, and a closed set of two
 * pure decisions is an enum rather than a strategy you inject.
 *
 * Deliberately *not* shared with {@see \Meraki\Schema\Field\Time\PrecisionPolicy}, which is the
 * same two cases over a different type: each field stays independent so it can gain a case the
 * other does not need without dragging it along.
 */
enum PrecisionPolicy: string
{
	/**
	 * Discard anything finer, so `2026-01-01T12:34:56` becomes `2026-01-01T12:34`.
	 *
	 * The usual choice. Extra precision is normally a medium's doing rather than something a
	 * person meant.
	 */
	case Truncate = 'truncate';

	/**
	 * Keep the value as submitted, so anything finer fails the field's `precision` constraint.
	 *
	 * A failure rather than an exception: input is attacker-controlled, and this used to throw
	 * from inside the parse, which escaped the field's shape check and surfaced as a 500.
	 */
	case Reject = 'reject';

	public function applyTo(LocalDateTime $dateTime, TimePrecision $precision): LocalDateTime
	{
		return match ($this) {
			self::Truncate => $precision->truncate($dateTime),
			self::Reject => $dateTime,
		};
	}

	/**
	 * Whether extra precision is a failure rather than something to discard.
	 *
	 * When false, nothing is being asked of the value's precision and the field's `precision`
	 * constraint is skipped.
	 */
	public function rejectsExtraPrecision(): bool
	{
		return $this === self::Reject;
	}
}
