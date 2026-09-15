<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\CreditCard;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field\HasParts;
use Meraki\Schema\Field\ParsedValue;
use Brick\DateTime\LocalDate;
use Brick\DateTime\DateTimeException;
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
	 * @param string|null $number digits only, with any spacing already removed
	 * @param LocalDate|null $expiry the *last* day of the month the card expires in, since a card
	 *        is good through the end of its stated month
	 * @param string|null $securityCode the only optional part
	 */
	public function __construct(
		#[SensitiveParameter] public ?string $number = null,
		public ?LocalDate $expiry = null,
		public ?string $name = null,
		#[SensitiveParameter] public ?string $securityCode = null,
	) {
	}

	/**
	 * Reads the array a form submits. Total: there is nothing it refuses, because it runs on
	 * untrusted input and an unreadable part is something to report rather than raise about.
	 *
	 * The number has its spacing stripped, because people type cards in groups of four. An expiry
	 * may arrive as `YYYY-MM` — which is what `<input type="month">` submits — or as a full date.
	 *
	 * @param array<string, mixed> $parts
	 */
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

	public static function fromInput(#[SensitiveParameter] array $parts): self
	{
		// Absent or not a string is null; `''` is kept, because submitting it was a decision.
		// Nothing is trimmed — that is the port's job.
		$text = static function (string $key) use ($parts): ?string {
			$value = $parts[$key] ?? null;

			return is_string($value) ? $value : null;
		};

		$number = $text('number');

		return new self(
			// The one thing that *is* canonicalised: ISO/IEC 7812 says a PAN is digits, so the
			// grouping people type it in is a display convention rather than part of the number.
			number: $number === null ? null : preg_replace('/\s+/', '', $number),
			expiry: self::readExpiry($text('expiry')),
			name: $text('name'),
			securityCode: $text('security_code'),
		);
	}

	/**
	 * The last day of the stated month, or `null` when there is no month to read.
	 *
	 * The last day rather than the first because a card expiring in `2026-09` is good until the end
	 * of September. Taking the first would reject a valid card for up to thirty days.
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
	/**
	 * Whether nothing at all was supplied. A card with no parts is not a card, so the field reads
	 * it as unreadable rather than as a half-filled one — {@see \Meraki\Schema\Field\CreditCard}.
	 */
	public function isEmpty(): bool
	{
		return $this->number === null
			&& $this->expiry === null
			&& $this->name === null
			&& $this->securityCode === null;
	}

	public function lastFourDigits(): ?string
	{
		return $this->number === null || strlen($this->number) < 4 ? null : substr($this->number, -4);
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
	public static function partNames(): array
	{
		return ['number', 'expiry', 'name', 'security_code'];
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
}
