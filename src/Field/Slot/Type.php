<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Slot;

/**
 * What a slot is named by: a day, a date and time, or a time of day.
 *
 * A hotel's nights are days, a clinic's appointments are dates and times, and a daily pickup is a
 * time. The {@see Source} declares which it offers, because it is the thing that knows, and the
 * field takes the type from it rather than being told twice — so the two cannot disagree.
 *
 * Backed by the names a port writes down, spelled the way the rest of the library spells a
 * multi-word case.
 */
enum Type: string
{
	case Date = 'date';
	case DateTime = 'date-time';
	case Time = 'time';
}
