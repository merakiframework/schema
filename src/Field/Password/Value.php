<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Password;

use Meraki\Schema\Field\ParsedValue;
use SensitiveParameter;

/**
 * One memorized secret, as this library compares it.
 *
 * ### Deliberately not printable
 *
 * There is no `__toString()`, for the same reason {@see \Meraki\Schema\Field\CreditCard\Value} has
 * none: stringifying is what makes a secret end up somewhere it should not be. A template that
 * interpolates a value, a log line that formats a result, a debug dump of a form — each of those is
 * one accidental `"{$value}"` away from writing a password down, and a class that offers the method
 * is inviting it.
 *
 * The secret is not hidden from the code that legitimately needs it. Something downstream has to
 * hash it, so masking here would only mean that code reaching past this object for the real thing.
 * {@see self::$secret} is public and exact. `#[SensitiveParameter]` is the guard that actually
 * helps: it keeps the value out of a stack trace without keeping it from the caller.
 *
 * Nothing here belongs in a session, a log or a database. What gets stored is the hash, and hashing
 * is not this field's concern — see {@see \Meraki\Schema\Field\Password} for why, and for the
 * bcrypt truncation that bites people who assume otherwise.
 */
final readonly class Value implements ParsedValue
{
	public function __construct(#[SensitiveParameter] public string $secret)
	{
	}

	/**
	 * Exact, and byte-for-byte.
	 *
	 * No case folding, no trimming, no Unicode normalisation: every one of those would mean two
	 * different secrets are accepted as one, which is the same as making the secret shorter.
	 *
	 * This is *not* a credential check and must never be used as one. It answers "are these the
	 * same string" for a collection deciding whether two rows repeat; verifying a password against
	 * a stored hash is `password_verify()`'s job, which is constant-time where this is not.
	 */
	public function equals(ParsedValue $other): bool
	{
		return $other instanceof self && $this->secret === $other->secret;
	}
}
