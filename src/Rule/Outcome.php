<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Field;
use Meraki\Schema\FieldScope;

/**
 * One change a rule makes to one field.
 *
 * An outcome is an *operation*, not a replacement: it takes the field as it currently stands
 * and hands back the field as it should now stand. Two reasons, both learned the hard way and
 * recorded in docs/ROADMAP.md — operations compose where whole-object snapshots clobber each
 * other, and an operation can still be asked what it did (`$applied->outcome->changes`)
 * where a replaced field has no intent left to read.
 *
 * Almost every outcome is an {@see Outcome\Reconfigure}, read from a field the author configured
 * through its own withers — so every configuration method a field has is a rule outcome, and
 * this interface has one other implementation rather than one per verb. {@see Outcome\Ignore}
 * is that other: ignoring is about a request rather than a definition, so no wither expresses
 * it.
 *
 * It is handed a field rather than the schema. Reaching the schema is what let an outcome
 * write to a shared definition, and it also meant every outcome had to find its own field
 * before changing it; now {@see \Meraki\Schema\Rule\Set} does the lookup once and the outcome
 * only knows how to transform what it is given.
 */
interface Outcome
{
	/**
	 * The field this changes, as it should stand afterwards. Returning the same instance is
	 * how an outcome says it changes nothing about the definition — see {@see Outcome\Ignore}.
	 */
	public function applyTo(Field $field): Field;

	/**
	 * Which field it acts on. Always a {@see FieldScope}: an outcome changes a field, and a
	 * path naming a value or a definition property is refused where the rule is written.
	 */
	public function getScope(): FieldScope;
}
