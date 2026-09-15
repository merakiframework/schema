<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\EmailAddress;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field\ParsedValue;
/**
 * One email address, split at the `@` and canonicalised.
 *
 * ### The one thing it changes, and the one thing it does not
 *
 * **The domain is lower-cased.** DNS is case-insensitive, so `EXAMPLE.TEST` and `example.test`
 * are the same host — not two spellings a field should tell apart. That is the whole test for
 * whether something belongs here: *does a standard say these two forms are one thing?*
 *
 * **The local part is left exactly as it came.** RFC 5321 permits a mailbox to be
 * case-sensitive, so `Alice@example.test` and `alice@example.test` may be two different people.
 * Lowercasing it would be a guess about someone's mail server, which is the kind of repair this
 * library leaves to its ports — see docs/CODING-STYLE.md.
 *
 * Nothing is trimmed, because `" a@b.test "` is not an address under the grammar and repairing it
 * would be guessing too. There is deliberately no `__toString()` either: this is the field's
 * internal representation, and {@see self::address()} is how you ask for the text.
 */
final readonly class Value implements ParsedValue
{
	/**
	 * @param string $localPart everything before the last `@`, exactly as submitted
	 * @param string $domain everything after it, lower-cased
	 */
	public function __construct(
		public string $localPart,
		public string $domain,
	) {
	}

	/**
	 * Splits an address that has already been checked against the grammar.
	 *
	 * `null` when there is no `@` at all, so this stays total — the field calls it after its
	 * pattern has matched, but nothing here depends on that having happened.
	 */
	/**
	 * Both halves exactly, because both arrive canonicalised: the domain is already lower-cased
	 * here — DNS says two spellings of a host are one host — and the local part deliberately is
	 * not, since RFC 5321 leaves its case to the receiving server and folding it would merge two
	 * mailboxes that a server is entitled to treat as different.
	 */
	public function equals(Equality $other): bool
	{
		return $other instanceof self
			&& $this->localPart === $other->localPart
			&& $this->domain === $other->domain;
	}

	public static function fromString(string $address): ?self
	{
		$at = strrpos($address, '@');

		if ($at === false) {
			return null;
		}

		return new self(
			substr($address, 0, $at),
			strtolower(substr($address, $at + 1)),
		);
	}

	/**
	 * The address as one string, with the domain in its canonical form.
	 *
	 * A method rather than a property because a `readonly` class cannot compute one on read — PHP
	 * refuses property hooks there, virtual ones included.
	 */
	public function address(): string
	{
		return $this->localPart . '@' . $this->domain;
	}
}
