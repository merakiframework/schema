<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\CreditCard;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Exception\BrokenInputContract;
use Meraki\Schema\Field;
use Meraki\Schema\Field\HasParts;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Field\ParsedValue;
use Brick\DateTime\DateTimeException;
use Brick\DateTime\LocalDate;
use SensitiveParameter;

/**
 * One payment card, held whole.
 *
 * Every part is nullable, and that is not laxity: a card needs a number, an expiry and a name, and
 * reporting *which* of them is missing is more use than refusing to build the object at all. The
 * field's constraints say what is required; this says what arrived.
 *
 * ### Two things this deliberately does not do
 *
 * It does not store the card. This is a validation value object — it exists for the length of one
 * request, and nothing here belongs in a log, a session or a database. Handling a real card number
 * is PCI DSS territory; the point of checking the Luhn digit here is to catch a typo before a
 * processor does, not to become a place cards live.
 *
 * Nor does it hide the number from its own consumer. Something downstream has to send the card to a
 * processor, so masking here would only mean that code reaching past this object for the real
 * thing. {@see self::lastFourDigits()} is there for display; the guard that matters is
 * `#[SensitiveParameter]`, which keeps the number out of a stack trace without keeping it from the
 * caller. There is deliberately no `__toString()`: a card number is not something to print by
 * accident, and that is exactly what stringifying invites.
 *
 * It does not guess the network. A number beginning `4` is probably a Visa, and "probably" is not
 * a thing to validate against — the ranges move, and the processor is the authority.
 */
final readonly class Value implements ParsedValue, HasParts
{
	/**
	 * The PAN with its grouping removed, or null when none was given.
	 *
	 * No `#[SensitiveParameter]`: the attribute targets parameters, and PHP refuses it on a
	 * property. The secrecy this type does carry is in what it withholds — there is no
	 * `__toString()`, and no masking either, because a consumer may legitimately need the digits.
	 */
	public ?string $number;

	/** The last day of the stated month, or null. */
	public ?LocalDate $expiry;

	/** The cardholder's name as printed, or null. */
	public ?string $name;

	/** The only part a card may legitimately be missing, since it is not always asked for. */
	public ?string $securityCode;

	/**
	 * Takes the record a field takes, so there is one answer to "what is a card here".
	 *
	 * Total about the *parts*: an absent or non-string part becomes null, because a
	 * half-filled card is still a card and the required-part constraints are what say which
	 * halves are missing. It refuses only a card with nothing in it at all, which is not a
	 * vague card — it is not a card.
	 *
	 * @param object{number?: string|null, expiry?: string|null, name?: string|null, security_code?: string|null} $card
	 * @throws BrokenInputContract if it carries a key a card does not have
	 * @throws MalformedValue if every part is absent
	 */
	public function __construct(#[SensitiveParameter] object $card)
	{
		$parts = get_object_vars($card);
		$unknown = array_diff(array_keys($parts), self::partNames());

		// Raised, not reported. Only the key *names* reach the message — never a value, on a
		// type where a value is a card number.
		if ($unknown !== []) {
			throw BrokenInputContract::recordHasKeysItDoesNotAccept(self::class, array_values($unknown), self::partNames());
		}

		// Absent or not a string is null. A part that was *sent* and holds nothing is unreadable
		// rather than absent — the rule every record-shaped value here follows — because `''` was
		// a decision somebody made, and reading it as "no expiry" would satisfy `expiryRequired`.
		// Nothing is trimmed beyond that; repairing input is the port's job.
		$text = static function (string $key) use ($parts): ?string {
			$value = $parts[$key] ?? null;

			if (!is_string($value)) {
				return null;
			}

			if (trim($value) === '') {
				throw MalformedValue::of(self::class, "its {$key} was given but holds nothing");
			}

			return $value;
		};

		$number = $text('number');

		// The one thing that *is* canonicalised: ISO/IEC 7812 says a PAN is digits, so the
		// grouping people type it in is a display convention rather than part of the number.
		$this->number = $number === null ? null : preg_replace('/\s+/', '', $number);
		$expiry = $text('expiry');
		$this->expiry = $expiry === null ? null : self::readExpiry($expiry);

		if ($expiry !== null && $this->expiry === null) {
			throw MalformedValue::of(self::class, sprintf('"%s" is not an expiry date', $expiry));
		}
		$this->name = $text('name');
		$this->securityCode = $text('security_code');

		if ($this->number === null && $this->expiry === null && $this->name === null && $this->securityCode === null) {
			throw MalformedValue::of(self::class, 'it has no number, expiry, name or security code');
		}
	}

	/**
	 * The readable way to write one by hand — a rule's bound, a test.
	 *
	 * A convenience over the constructor rather than a second way in: it builds the record a
	 * form would submit and hands it over, so the invariant is enforced in one place.
	 *
	 * @throws MalformedValue if every part is absent
	 */
	public static function of(
		#[SensitiveParameter] ?string $number = null,
		?string $expiry = null,
		?string $name = null,
		#[SensitiveParameter] ?string $securityCode = null,
	): self {
		return new self((object) [
			'number' => $number,
			'expiry' => $expiry,
			'name' => $name,
			'security_code' => $securityCode,
		]);
	}

	/**
	 * The same card: number, expiry and holder.
	 *
	 * The security code is deliberately left out. It is not part of what identifies a card — it is
	 * a per-transaction proof, it may not have been asked for at all, and two entries of one card
	 * that differ only in whether the code was captured are still one card. Including it would make
	 * a collection accept the same card twice.
	 *
	 * The number is compared in full, having already had its spacing stripped on the way in, so
	 * `4014 1828 2909 8807` and `4014182829098807` are one card.
	 */
	public function equals(Equality $other): bool
	{
		return $other instanceof self
			&& $this->number === $other->number
			&& $this->name === $other->name
			&& (
				$this->expiry === null
					? $other->expiry === null
					: $other->expiry !== null && $this->expiry->isEqualTo($other->expiry)
			);
	}

	/**
	 * The last day of the stated month, or `null` when there is no month to read.
	 *
	 * The last day rather than the first because a card expiring in `2026-09` is good until the end
	 * of September. Taking the first would reject a valid card for up to thirty days.
	 *
	 * Either spelling is read: `YYYY-MM`, which is what `<input type="month">` submits, or a full
	 * date. Anything else is `null` — unreadable input is something to report rather than raise
	 * about, because this runs on what a stranger typed.
	 */
	private static function readExpiry(?string $expiry): ?LocalDate
	{
		if ($expiry === null) {
			return null;
		}

		if (preg_match('/^(\d{4})-(\d{2})$/', $expiry, $parts) === 1) {
			try {
				return LocalDate::of((int) $parts[1], (int) $parts[2], 1)->withDay(1)->plusMonths(1)->minusDays(1);
			} catch (DateTimeException) {
				return null;
			}
		}

		try {
			return LocalDate::parse($expiry);
		} catch (DateTimeException) {
			return null;
		}
	}



	/**
	 * The last four digits, which is the most of a card number anything should ever show.
	 */
	public function lastFourDigits(): ?string
	{
		return ($this->number === null || strlen($this->number) < 4) ? null : substr($this->number, -4);
	}

	/**
	 * Whether this holds enough to be a card at all: a number, an expiry and a name. The security
	 * code is the one part a card can do without — plenty of flows never ask for one.
	 *
	 * Which is why an empty card is *invalid* rather than absent: submitting a card means
	 * submitting those three.
	 */
	public function isComplete(): bool
	{
		return $this->number !== null && $this->expiry !== null && $this->name !== null;
	}

	/**
	 * The number and the security code are here because a part scope reads what the field
	 * holds, and withholding them would only send a caller to the properties instead. Nothing
	 * about a part scope makes a card safe to log — see this class's note on that.
	 *
	 * @return list<string>
	 */
	private static function partNames(): array
	{
		return array_column(Part::cases(), 'value');
	}

	/**
	 * @return array<string, mixed>
	 */
	public function parts(): array
	{
		return [
			'number' => $this->number,
			'expiry' => $this->expiry,
			'name' => $this->name,
			'security_code' => $this->securityCode,
		];
	}

	/**
	 * Nothing here is canonicalised, so a rule compares against exactly what it was written
	 * with. {@see \Meraki\Schema\Field\Address\Value::canonicalPartValue()} is the one that
	 * has work to do.
	 */
	public function canonicalPartValue(Field\Part $part, mixed $expected): mixed
	{
		return $expected;
	}

}
