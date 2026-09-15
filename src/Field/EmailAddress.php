<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\EmailAddress\Value;
use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;
use InvalidArgumentException;

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
 * local part from RFC 5321 is checked separately; see {@see self::validateValue()}.
 *
 * @extends AtomicField<string|null>
 *
 * The parsed form is an {@see Value}: the local part as submitted, the domain lower-cased.
 * @see https://html.spec.whatwg.org/multipage/input.html#valid-e-mail-address
 */
final readonly class EmailAddress extends AtomicField
{
	/** @see https://html.spec.whatwg.org/multipage/input.html#valid-e-mail-address */
	private const PATTERN = '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/';

	/** `a@b` is the shortest thing the grammar accepts, so no author may ask for less. */
	public const SHORTEST = 3;

	/**
	 * RFC 5321 caps a forward path at 256 octets including the angle brackets, leaving 254 for
	 * the address. No author may ask for more, because an address longer than this cannot be
	 * delivered however well-formed it looks.
	 */
	public const LONGEST = 254;

	/**
	 * RFC 5321 §4.5.3.1.1. Checked as *shape* rather than as a constraint: an over-long local
	 * part is malformed, not well-formed-but-disallowed.
	 *
	 * Its counterpart — the 255-octet limit on the domain — needs no check, because
	 * {@see self::LONGEST} already bounds the whole address well below it.
	 */
	private const LONGEST_LOCAL_PART = 64;

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

		$this->minLength = self::SHORTEST;
		$this->maxLength = self::LONGEST;
		$this->allowedDomains = [];
		$this->disallowedDomains = [];
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * @param int<self::SHORTEST, self::LONGEST> $minChars
	 * @throws InvalidArgumentException if shorter than `a@b`, or above the maximum
	 */
	public function minLengthOf(int $minChars): static
	{
		if ($minChars < self::SHORTEST) {
			throw new InvalidArgumentException(sprintf(
				'A minimum length below %d cannot reject anything: no shorter address is well-formed.',
				self::SHORTEST,
			));
		}

		if ($minChars > $this->maxLength) {
			throw new InvalidArgumentException('A minimum length cannot exceed the maximum.');
		}

		return $this->with(['minLength' => $minChars]);
	}

	/**
	 * @param int<self::SHORTEST, self::LONGEST> $maxChars
	 * @throws InvalidArgumentException if above what can be delivered, or below the minimum
	 */
	public function maxLengthOf(int $maxChars): static
	{
		if ($maxChars > self::LONGEST) {
			throw new InvalidArgumentException(sprintf(
				'A maximum length above %d would accept addresses that cannot be delivered.',
				self::LONGEST,
			));
		}

		if ($maxChars < $this->minLength) {
			throw new InvalidArgumentException('A maximum length cannot be less than the minimum.');
		}

		return $this->with(['maxLength' => $maxChars]);
	}

	/**
	 * Adds to the accepted domains. Accumulates, like every other `allow*()`; lifting the
	 * restriction is {@see self::clearAllowedDomains()}.
	 *
	 * @param non-empty-string $domain
	 * @param non-empty-string ...$domains
	 * @throws InvalidArgumentException if a domain is empty
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
	 * @throws InvalidArgumentException if a domain is empty
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
	 * The WHATWG grammar, plus the one limit it cannot express.
	 *
	 * The local-part length belongs here rather than in a constraint because an address whose
	 * local part exceeds 64 octets is malformed — there is no configuration under which it would
	 * be acceptable — and reporting it as "too long" would suggest the author could allow it.
	 */
	protected function parse(mixed $value): ?Value
	{
		if (!is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
			return null;
		}

		// The grammar guarantees an @ once the pattern has matched, so this cannot be null — but
		// fromString() says so itself rather than being trusted to.
		$address = Value::fromString($value);

		if ($address === null) {
			return null;
		}

		// RFC 5321 caps the local part at 64 octets, which the grammar cannot express.
		return strlen($address->localPart) > self::LONGEST_LOCAL_PART ? null : $address;
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
				throw new InvalidArgumentException('A domain cannot be empty.');
			}
		}

		return array_values(array_unique([...$existing, ...$additional]));
	}

	private function meetsMinimumLength(Value $address): bool
	{
		return mb_strlen($address->address()) >= $this->minLength;
	}

	private function meetsMaximumLength(Value $address): bool
	{
		return mb_strlen($address->address()) <= $this->maxLength;
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
