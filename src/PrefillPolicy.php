<?php
declare(strict_types=1);

namespace Meraki\Schema;

/**
 * Whether a prefilled value has to satisfy the field it lands in.
 *
 * Only applies to a value that actually survived as {@see ValueSource::Prefilled} — trust attaches
 * to the value, not to the request, so it cannot excuse anything the user typed over the top.
 */
enum PrefillPolicy
{
	/**
	 * Check it like any other value. The default, and the right one nearly always.
	 *
	 * The scenario that decides it: a constraint tightens — a minimum length goes up, a domain
	 * comes off the allow-list — and values already in the database no longer satisfy it. Checked
	 * surfaces that on the form, where the user can fix it. Trusted hides it until something
	 * downstream chokes on data the schema said was fine.
	 */
	case Checked;

	/**
	 * Take it as given, and skip the constraints.
	 *
	 * For a value the application already vouches for and the user cannot be asked about — a
	 * legacy record being edited for one field, an identifier the form shows but does not own.
	 *
	 * The *shape* is still checked, deliberately. Trust is a statement about whether a value meets
	 * the rules, not about whether the field can read it at all: a value it cannot parse leaves
	 * nothing to hand back, so calling it acceptable would mean reporting success and a null
	 * value together.
	 */
	case Trusted;
}
