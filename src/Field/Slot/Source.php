<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Slot;

/**
 * Where a {@see \Meraki\Schema\Field\Slot} finds out what is on offer.
 *
 * The application implements it, over whatever holds its slots: a table, a calendar, a booking
 * service. The field asks about one slot at a time and never for the list, because a definition
 * cannot hold eighteen months of them and validating one value needs only one answer.
 *
 * ### It lives as long as the schema does
 *
 * A source is a *source* of answers rather than the answers, the way a clock is a source of the
 * instant, and the field holding it is built once and shared across requests. So it holds nothing
 * about any one request: a repository over a connection is fine, and a list loaded for the
 * current user is not.
 *
 * ### What it does not do
 *
 * List slots. A picker needs a week at a time, and that is the port's and the application's
 * business — an endpoint the port finds by {@see self::$id}. Nothing in the core would call it.
 */
interface Source
{
	/** What this source is called wherever a schema is written down, and what a port finds it by. */
	public SourceId $id { get; }

	/** The type of slot it offers. Every slot it is asked about is of this type. */
	public Type $slotType { get; }

	/**
	 * Whether this slot is on offer.
	 *
	 * Asked once per value, while it is validated, so the answer is the source's at that moment and
	 * may be different a second later. That makes it advice to the form: the booking itself is
	 * what has to refuse a slot that went in the meantime.
	 *
	 * Throwing is allowed and is not caught — the request fails. Answer
	 * {@see Availability::CannotCheck} to carry on unchecked instead. Which is right is the
	 * application's call, so neither is made for it.
	 */
	public function availabilityOf(Value $slot): Availability;
}
