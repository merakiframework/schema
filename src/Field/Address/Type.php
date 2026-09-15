<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Address;

/**
 * What an address is for, and therefore what it must be capable of.
 *
 * A superset of HL7 FHIR's `Address.type` value set: `postal`, `physical` and `both`
 * keep their FHIR meanings, and `either` — the default — is ours, expressing "no
 * restriction" the way {@see \Meraki\Schema\Field\PhoneNumber\Type::Any} does.
 *
 * Only the two that promise a *visitable* location reject PO-Box-style lines:
 *
 *  - `Either`   — either purpose is acceptable. Accepts everything.
 *  - `Postal`   — must be mailable. Accepts everything today; declares intent, and is
 *                 the hook for a future "is actually deliverable" check.
 *  - `Physical` — must be somewhere you can go. Rejects PO boxes.
 *  - `Both`     — must be mailable *and* visitable. Rejects PO boxes.
 */
enum Type: string
{
	case Either = 'either';
	case Postal = 'postal';
	case Physical = 'physical';
	case Both = 'both';

	/**
	 * Whether an address of this type must be a place that can be physically visited,
	 * and so cannot be a post-office box or bag service.
	 */
	public function requiresVisitableLocation(): bool
	{
		return $this === self::Physical || $this === self::Both;
	}

	/**
	 * Whether an address of this type must be something the post can reach, and so cannot be just
	 * an area — you cannot post to a suburb.
	 *
	 * Separate from {@see self::requiresVisitableLocation()} because the two questions are
	 * independent: a PO box is deliverable and not visitable, and a service area covering a whole
	 * suburb is visitable and not deliverable.
	 */
	public function requiresDeliverability(): bool
	{
		return $this === self::Postal || $this === self::Both;
	}

	/**
	 * This type, narrowed so that mailability is also required.
	 *
	 * Narrowing rather than setting, so that asking for both restrictions in either order lands on
	 * {@see self::Both} rather than the second call undoing the first.
	 */
	public function narrowedToMailable(): self
	{
		return match ($this) {
			self::Either, self::Postal => self::Postal,
			self::Physical, self::Both => self::Both,
		};
	}

	/**
	 * This type, narrowed so that a visitable location is also required.
	 */
	public function narrowedToPhysical(): self
	{
		return match ($this) {
			self::Either, self::Physical => self::Physical,
			self::Postal, self::Both => self::Both,
		};
	}
}
