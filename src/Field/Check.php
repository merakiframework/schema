<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use BackedEnum;

/**
 * Something a field checks, named by the code a failure is reported under.
 *
 * Each field declares its own as a string-backed enum — `Text\Check::MinLength` — and the case's
 * value is the name a language pack writes a message under: `minLength`. An interface extending
 * {@see \BackedEnum} is one only an enum can implement, so PHP itself refuses a code that is not a
 * case of a closed list. A code is never a string somebody typed where it was used; the string is
 * what it becomes on the wire, for a pack or a serialised schema to read.
 *
 * One enum per field rather than one shared list, deliberately. `Text\Check::MinLength` and
 * `Password\Check::MinLength` share a wire name, so one sentence in a pack can serve both, and are
 * different types, so nothing outside a field depends on another field's vocabulary — which is
 * what lets a field live in a package of its own.
 *
 * ### The part is fixed per check
 *
 * {@see self::part()} names the part of a structured value the check concerns, or `null` when it
 * concerns the whole value. It belongs to the code rather than to each use of it, which is what
 * lets every key a pack may define be listed — and every failure be put beside the right input —
 * before anything has been validated. A check about two parts is two checks.
 */
interface Check extends BackedEnum
{
	/**
	 * The part this check concerns, or `null` when it is about the value as a whole.
	 */
	public function part(): ?Part;
}
