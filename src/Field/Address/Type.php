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
}
