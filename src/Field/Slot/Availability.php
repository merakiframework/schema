<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Slot;

/**
 * What a {@see Source} says about one slot.
 *
 * Three answers rather than a nullable boolean, so an adapter says what happened instead of
 * encoding it. `null` reads as "no" as easily as "don't know", and those are different verdicts:
 * {@see self::Unavailable} fails the `available` constraint, and {@see self::CannotCheck} skips it.
 */
enum Availability
{
	/** The source is offering this slot. */
	case Available;

	/** The source is not offering it — taken, never offered, or outside what it offers at all. */
	case Unavailable;

	/**
	 * The source could not be asked — down, timed out, refused — so nothing was learned about the
	 * slot, which may well be free. The constraint is skipped rather than failed.
	 *
	 * An adapter that would rather fail the request than carry on unchecked throws instead. The
	 * field does not catch it.
	 */
	case CannotCheck;
}
