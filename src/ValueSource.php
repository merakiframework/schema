<?php
declare(strict_types=1);

namespace Meraki\Schema;

/**
 * Where the value a field was judged on actually came from.
 *
 * Three different things used to be one `$value` with no way to tell them apart, and a consumer
 * that needs to know has to be told: a form marks a prefilled field differently from one the user
 * typed into, and "you left this as the default" is not the same message as "you entered this".
 *
 * It is also what makes the defaults/prefill split visible. An authored default is a constant in
 * the schema and serialises with it; a prefill is one user's data, arrives with the request, and
 * must never touch the definition — see {@see Facade::validate()}.
 */
enum ValueSource
{
	/** The request carried it. Beats everything else, because the user just said so. */
	case Submitted;

	/**
	 * Looked up for this one user and passed in with the request — their saved address, their
	 * stored email.
	 *
	 * Beats the authored default and loses to what was submitted. Never reaches the definition.
	 */
	case Prefilled;

	/** The constant the schema's author wrote, standing in because nothing else did. */
	case Default;

	/** Nothing was submitted, nothing was prefilled, and the field has no default. */
	case None;
}
