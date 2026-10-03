<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use BackedEnum;

/**
 * One named part of a structured value — an amount's currency, an address's postcode.
 *
 * Each kind of value declares its parts as a string-backed enum — `Address\Part::PostalCode` — and
 * a case's value is the part's name as it arrives and as it is reported: `postal_code`. That is the
 * key submitted input uses, the part a {@see \Meraki\Schema\PartScope} names, and the `part.*` key
 * a language pack translates. Like {@see Check}, only an enum can implement this.
 *
 * The enum is the one place a part is described. Whether a value can exist without it and whether
 * it holds a list are facts about the part rather than about any field, so they are declared here
 * and read from here — by {@see \Meraki\Schema\Field::$essentialParts}, by a rule refusing to order
 * a list, by a port deciding which boxes to draw.
 */
interface Part extends BackedEnum
{
	/**
	 * Whether no value of this kind can exist without this part, whatever a field asks for.
	 *
	 * A fact about the kind of value, not configuration: a phone number is a number *in a
	 * country*, so both are essential to every phone number field there will ever be. A part a
	 * particular field merely asks for — an address's street, above its precision floor — is not
	 * essential, because another field may decline it.
	 */
	public function isEssential(): bool;

	/**
	 * Whether this part holds a list of entries rather than one.
	 *
	 * An address's street is one of up to three lines, and nothing here joins them into delimited
	 * text. A list has no order, so a rule asking `isAtLeast()` of one is refused where it is
	 * written; the textual verbs ask their question of each entry instead.
	 */
	public function isList(): bool;
}
