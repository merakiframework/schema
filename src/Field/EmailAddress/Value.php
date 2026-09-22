<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\EmailAddress;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field\HasParts;
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
 * would be guessing too.
 *
 * ### It reads back as one string
 *
 * This used to say there was deliberately no `__toString()`, on the grounds that the split form
 * is the field's internal representation and a method was how you asked for the text. That
 * argument does not survive contact with the rest of the library: {@see \Meraki\Schema\Field\Uri\Value}
 * and {@see \Meraki\Schema\Field\Uuid\Value} are equally internal representations and both read
 * back, and the *existence* of a single canonical spelling is the whole test — which the old
 * `address()` method proved by being able to produce one.
 *
 * It also cost something real. A value with no string form gets
 * {@see \Meraki\Schema\Rule\Matcher\Basic}, so an email field offered no `matches` and a rule
 * could not check a domain — which is among the likelier things to want from an email address.
 *
 * The absences that *are* deliberate are {@see \Meraki\Schema\Field\Password\Value} and
 * {@see \Meraki\Schema\Field\CreditCard\Value}, and the reason there is not "it is internal" —
 * it is that a rule must not be able to read a secret by accident.
 */
final readonly class Value implements ParsedValue, HasParts
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
	 * Replaces an `address()` method that returned exactly this. Two spellings of one string is
	 * what every other value here avoids, and this is the one the language already knows about —
	 * it is what makes the value `Stringable`, and so what earns the field its text matchers.
	 */
	public function __toString(): string
	{
		return $this->localPart . '@' . $this->domain;
	}

	/**
	 * Split as RFC 5321 splits it. The domain is already lower-cased and the local part
	 * deliberately is not, so comparing two `domain` parts is the reliable half of comparing two
	 * addresses.
	 *
	 * @return list<string>
	 */
	public static function partNames(): array
	{
		return ['local_part', 'domain'];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function parts(): array
	{
		return [
			'local_part' => $this->localPart,
			'domain' => $this->domain,
		];
	}
}
