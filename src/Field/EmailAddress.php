<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Exception\InvalidConfiguration;
use Meraki\Schema\ValueScope;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Field\EmailAddress\Value;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;

/**
 * An email address, as the WHATWG HTML specification defines one.
 *
 * That grammar rather than RFC 5322's, and deliberately: the HTML spec calls its own definition
 * "a willful violation of RFC 5322" because the RFC permits comments, folding whitespace and
 * quoted strings that no address in use needs and no form should accept. It is the definition
 * every browser already enforces on `<input type="email">`, so a field that agreed with the RFC
 * instead would accept addresses the user's own browser had already refused to submit.
 *
 * Two consequences of following it to the letter. `jane..doe@example.test` and
 * `.jane@example.test` are **accepted** — the WHATWG grammar does not restrict dot placement in
 * the local part, and diverging there would mean disagreeing with the browser. But the
 * specification's pattern cannot express a length limit on a repeated group, so the 64-octet
 * local part from RFC 5321 is checked separately — both of them in {@see Value}, which is where
 * "is this an address" is decided.
 *
 * @extends AtomicField<string|null>
 *
 * The parsed form is an {@see Value}: the local part as submitted, the domain lower-cased.
 * @see https://html.spec.whatwg.org/multipage/input.html#valid-e-mail-address
 */
final readonly class EmailAddress extends AtomicField
{
	/** `a@b` is the shortest thing the grammar accepts, so no author may ask for less. */
	public const SHORTEST = 3;

	/**
	 * RFC 5321 caps a forward path at 256 octets including the angle brackets, leaving 254 for
	 * the address. No author may ask for more, because an address longer than this cannot be
	 * delivered however well-formed it looks.
	 */
	public const LONGEST = 254;

	/** @var int<self::SHORTEST, self::LONGEST> */
	public int $minLength;

	/**
	 * Not nullable, unlike every other field's. There is nothing to clear it *to*: the standard's
	 * own ceiling is the only sensible default, and an address above it is undeliverable rather
	 * than merely long.
	 *
	 * @var int<self::SHORTEST, self::LONGEST>
	 */
	public int $maxLength;

	/**
	 * Domains this field will accept. Empty means any.
	 *
	 * Matched whole, so `example.test` does not admit `mail.example.test`; a pattern may use `*`
	 * for one label, as in `*.example.test`.
	 *
	 * @var list<non-empty-string>
	 */
	public array $allowedDomains;

	/** @var list<non-empty-string> Empty means none is refused outright. */
	public array $disallowedDomains;

	public function __construct(
		public FieldName $name,
	) {
		parent::__construct();

		$this->minLength = self::initially(self::SHORTEST);
		$this->maxLength = self::initially(self::LONGEST);
		$this->allowedDomains = self::initially([]);
		$this->disallowedDomains = self::initially([]);
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * @param int<self::SHORTEST, self::LONGEST> $minChars
	 * @throws InvalidConfiguration if shorter than `a@b`, or above the maximum
	 */
	public function minLengthOf(int $minChars): static
	{
		if ($minChars < self::SHORTEST) {
			throw InvalidConfiguration::minimumLengthIsBelowWhatIsWellFormed(self::SHORTEST);
		}

		if ($minChars > $this->maxLength) {
			throw InvalidConfiguration::minimumExceedsMaximum('length');
		}

		return $this->with(['minLength' => $minChars]);
	}

	/**
	 * @param int<self::SHORTEST, self::LONGEST> $maxChars
	 * @throws InvalidConfiguration if above what can be delivered, or below the minimum
	 */
	public function maxLengthOf(int $maxChars): static
	{
		if ($maxChars > self::LONGEST) {
			throw InvalidConfiguration::maximumLengthIsAboveWhatCanBeDelivered(self::LONGEST);
		}

		if ($maxChars < $this->minLength) {
			throw InvalidConfiguration::maximumIsBelowMinimum('length');
		}

		return $this->with(['maxLength' => $maxChars]);
	}

	/**
	 * Adds to the accepted domains. Accumulates, like every other `allow*()`; lifting the
	 * restriction is {@see self::clearAllowedDomains()}.
	 *
	 * @param non-empty-string $domain
	 * @param non-empty-string ...$domains
	 * @throws InvalidConfiguration if a domain is empty
	 */
	public function allowDomains(string $domain, string ...$domains): static
	{
		return $this->with(['allowedDomains' => $this->merge($this->allowedDomains, [$domain, ...$domains])]);
	}

	/**
	 * Accepts any domain again. Leaves the refused domains alone.
	 */
	public function clearAllowedDomains(): static
	{
		return $this->with(['allowedDomains' => []]);
	}

	/**
	 * Adds to the refused domains — a disposable-address list, say.
	 *
	 * @param non-empty-string $domain
	 * @param non-empty-string ...$domains
	 * @throws InvalidConfiguration if a domain is empty
	 */
	public function disallowDomains(string $domain, string ...$domains): static
	{
		return $this->with(['disallowedDomains' => $this->merge($this->disallowedDomains, [$domain, ...$domains])]);
	}

	/**
	 * Refuses nothing outright again. Leaves the accepted domains alone.
	 */
	public function clearDisallowedDomains(): static
	{
		return $this->with(['disallowedDomains' => []]);
	}

	/**
	 * What a rule may ask about this field: an address reads back as one string, so it can be
	 * searched and pattern-matched — a domain check is among the likelier rules to want. There is
	 * no order: one address is not *before* another in any sense a form means.
	 *
	 * @see \Meraki\Schema\Rule\Matcher for the four sets and why a field declares one
	 */
	public function when(): Matcher\Text
	{
		return new Matcher\Text(ValueScope::of($this->name));
	}

	/**
	 * Hands the decision to {@see Value}, and turns its refusal into an absence.
	 *
	 * Nothing about *what an address is* lives here any more. The grammar and RFC 5321's
	 * 64-octet local part are facts about an address rather than about this field — no
	 * configuration makes them come out differently — and the value's own equality depends on the
	 * canonicalising that goes with them, so it has to be the value that enforces both. See
	 * {@see Value} for what that fixed.
	 *
	 * What is left is the one thing a field has to do that a value cannot: report rather than
	 * raise. `null` means *unreadable*, which is the shape failing — distinct from the
	 * constraints, which never ran.
	 */
	protected function parse(mixed $value): Value
	{
		// Its own value back, unchanged. A value is canonical by construction, so re-reading one
		// could only produce itself — and saying so directly beats relying on the round-trip to
		// prove it.
		if ($value instanceof Value) {
			return $value;
		}

		// The only narrowing this does. `mixed` is what a request hands over and `string` is what
		// the value takes, so something has to bridge them — and if the constructor were handed a
		// non-string directly it would raise a `TypeError`, which is not what a lifecycle catches.
		if (!is_string($value)) {
			throw MalformedValue::of(Value::class, 'an email address is submitted as a string');
		}

		return new Value($value);
	}

	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			new Constraint('minLength', $this->meetsMinimumLength(...), $this->minLength),
			new Constraint('maxLength', $this->meetsMaximumLength(...), $this->maxLength),
			new Constraint('allowedDomains', $this->isAnAllowedDomain(...), $this->allowedDomains),
			new Constraint('disallowedDomains', $this->isNotADisallowedDomain(...), $this->disallowedDomains),
		);
	}

	/**
	 * @param list<non-empty-string> $existing
	 * @param list<string> $additional
	 * @return list<non-empty-string>
	 */
	private function merge(array $existing, array $additional): array
	{
		foreach ($additional as $domain) {
			if ($domain === '') {
				throw InvalidConfiguration::listMemberIsEmpty('domain');
			}
		}

		return array_values(array_unique([...$existing, ...$additional]));
	}

	private function meetsMinimumLength(Value $address): bool
	{
		return mb_strlen((string) $address) >= $this->minLength;
	}

	private function meetsMaximumLength(Value $address): bool
	{
		return mb_strlen((string) $address) <= $this->maxLength;
	}

	private function isAnAllowedDomain(Value $address): ?bool
	{
		// No list means nothing was asked, so nothing was checked.
		if ($this->allowedDomains === []) {
			return null;
		}

		return $this->matchesAny($address->domain, $this->allowedDomains);
	}

	private function isNotADisallowedDomain(Value $address): ?bool
	{
		return $this->disallowedDomains === [] ? null : !$this->matchesAny($address->domain, $this->disallowedDomains);
	}

	/**
	 * @param list<non-empty-string> $patterns
	 */
	private function matchesAny(string $domain, array $patterns): bool
	{
		foreach ($patterns as $pattern) {
			// `*` stands for exactly one label, so `*.example.test` admits `mail.example.test`
			// but not `a.b.example.test`.
			$regex = '/^' . str_replace('\*', '[^.]+', preg_quote($pattern, '/')) . '$/i';

			if (preg_match($regex, $domain) === 1) {
				return true;
			}
		}

		return false;
	}
}
