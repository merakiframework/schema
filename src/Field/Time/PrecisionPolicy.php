<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Time;

use Brick\DateTime\LocalTime;

/**
 * What happens to precision the field did not ask for.
 *
 * Replaced a `PrecisionCaster` interface and its two implementations. Once neither of them was
 * allowed to throw, the only difference left between them was one line, and a closed set of two
 * pure decisions is an enum rather than a strategy you inject.
 *
 * Deliberately *not* shared with {@see \Meraki\Schema\Field\DateTime\PrecisionPolicy}, which is
 * the same two cases over a different type: each field stays independent so it can gain a case
 * the other does not need without dragging it along.
 */
enum PrecisionPolicy: string
{
	/**
	 * Discard anything finer, so `12:34:56` becomes `12:34`.
	 *
	 * The usual choice. Extra precision is normally a medium's doing — a browser widget or an
	 * API client sending `12:34:00.000` — rather than something a person meant.
	 */
	case Truncate = 'truncate';

	/**
	 * Keep the value as submitted, so anything finer fails the field's `precision` constraint.
	 *
	 * A failure rather than an exception: input is attacker-controlled, and this used to throw
	 * from inside the parse, which escaped the field's shape check and surfaced as a 500.
	 */
	case Reject = 'reject';

	public function applyTo(LocalTime $time, Precision $precision): LocalTime
	{
		return match ($this) {
			self::Truncate => $precision->truncate($time),
			self::Reject => $time,
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
