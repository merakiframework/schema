<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\EmailAddress;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field\HasParts;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Field\ParsedValue;

/**
 * One email address, split at the `@` and canonicalised.
 *
 * ### It cannot be built out of something that is not an address
 *
 * The grammar is checked here, in the constructor, so there is no way to hold one of these that a
 * field would not have produced. That used to live in {@see \Meraki\Schema\Field\EmailAddress::parse()},
 * and splitting it from the value cost something concrete: the domain is lower-cased on the way in
 * and {@see self::equals()} depends on that having happened, so a value built directly compared
 * unequal to the same address parsed by a field —
 *
 *     Value::fromString('kim@EXAMPLE.TEST')     // kim@example.test
 *     new Value('kim', 'EXAMPLE.TEST')          // kim@EXAMPLE.TEST — and not equal to it
 *
 * An invariant the value's own equality relies on has to be the value's to enforce. Now it is, and
 * the two spellings of the constructor are one.
 *
 * ### Shape, and only shape
 *
 * It raises {@see MalformedValue} for what is not an address at all. It says nothing about whether
 * an address is *acceptable* — a domain allow-list, a length bound — because those are the field's
 * constraints, and a constraint that raised here would report "unreadable" where it should report
 * which check failed and what the limit was.
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
 */
final readonly class Value implements ParsedValue, HasParts
{
	/**
	 * The addr-spec this accepts: a dot-atom local part, and a domain of LDH labels.
	 *
	 * Deliberately not the whole of RFC 5322 — no quoted strings, no comments, no address
	 * literals. Those are legal and essentially never wanted in a form, and every one of them is
	 * a way for something downstream to disagree with this about what the address was.
	 */
	private const PATTERN = '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/';

	/** RFC 5321 caps the local part at 64 octets, which the grammar cannot express. */
	private const LONGEST_LOCAL_PART = 64;

	/** Everything before the last `@`, exactly as given. */
	public string $localPart;

	/** Everything after it, lower-cased. */
	public string $domain;

	/**
	 * @param string $address the whole address, which is the only form the grammar can be checked
	 *        against — a local part and a domain handed over separately have already had the
	 *        decision made about where they split.
	 * @throws MalformedValue if this is not an address
	 */
	public function __construct(string $address)
	{
		if (preg_match(self::PATTERN, $address) !== 1) {
			throw MalformedValue::of(self::class, sprintf(
				'"%s" is not "something@a.domain"',
				$address,
			));
		}

		// The grammar guarantees an @ by here, so the split cannot fail.
		$at = strrpos($address, '@');
		$localPart = substr($address, 0, $at);

		if (strlen($localPart) > self::LONGEST_LOCAL_PART) {
			throw MalformedValue::of(self::class, sprintf(
				'the part before the @ is %d octets and RFC 5321 allows %d',
				strlen($localPart),
				self::LONGEST_LOCAL_PART,
			));
		}

		$this->localPart = $localPart;
		$this->domain = strtolower(substr($address, $at + 1));
	}

	/**
	 * Both halves exactly, because both arrive canonicalised: the domain is already lower-cased
	 * here — DNS says two spellings of a host are one host — and the local part deliberately is
	 * not, since RFC 5321 leaves its case to the receiving server and folding it would merge two
	 * mailboxes that a server is entitled to treat as different.
	 *
	 * Reliable now in a way it was not, because the canonicalising happens where the comparison
	 * does. There is no longer a way to build one of these that has skipped it.
	 */
	public function equals(Equality $other): bool
	{
		return $other instanceof self
			&& $this->localPart === $other->localPart
			&& $this->domain === $other->domain;
	}

	/**
	 * The address as one string, with the domain in its canonical form.
	 *
	 * Round-trips: `new Value((string) $value)` is `$value`, which is what makes it safe for a
	 * field to accept its own value back — see {@see \Meraki\Schema\Field\EmailAddress::parse()}.
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
