<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\CreditCard;

use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Field\ParsedValue;
use Brick\DateTime\LocalDate;
use SensitiveParameter;

/**
 * One payment card, held whole.
 *
 * A number and an expiry, neither of them null, and a name and a security code when they were
 * given. A card somebody has half filled in is an {@see Input} that has not made a value yet, and
 * is reported part by part, so nothing handed one of these has to ask which part is there.
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
final readonly class Value implements ParsedValue
{
	/**
	 * Built by {@see Input} from what was submitted, or by {@see self::of()} by hand. The
	 * constructor still guards what it can see, so a value made any other way cannot hold a number
	 * that is not one.
	 *
	 * @param string $number the PAN, digits only — the grouping people type it in is a display
	 *        convention rather than part of the number
	 * @param LocalDate $expiry the last day of the stated month
	 * @param string|null $name the cardholder's name as printed, or null when it was not asked for
	 * @param string|null $securityCode null when it was not asked for — plenty of flows never do
	 * @throws MalformedValue if the number is not 13 to 19 digits
	 */
	public function __construct(
		#[SensitiveParameter] public string $number,
		public LocalDate $expiry,
		public ?string $name = null,
		#[SensitiveParameter] public ?string $securityCode = null,
	) {
		if (preg_match('/^\d{13,19}$/', $number) !== 1) {
			throw MalformedValue::of(self::class, 'a card number is 13 to 19 digits');
		}
	}

	/**
	 * The readable way to write one by hand — a rule's bound, a test.
	 *
	 * Read the way a form's card is, through {@see Input}, so there is one place where what a card
	 * *is* gets decided.
	 *
	 * @throws MalformedValue if what it is given does not make a card
	 */
	public static function of(
		#[SensitiveParameter] string $number,
		string $expiry,
		?string $name = null,
		#[SensitiveParameter] ?string $securityCode = null,
	): self {
		$input = new Input((object) [
			'number' => $number,
			'expiry' => $expiry,
			'name' => $name,
			'security_code' => $securityCode,
		]);

		// The codes only: a message here must never carry what was typed.
		return $input->value ?? throw MalformedValue::of(self::class, sprintf(
			'it does not make a card: %s',
			implode(', ', array_map(static fn(Field\Violation $violation): string => $violation->name, $input->violations)),
		));
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
			&& $this->expiry->isEqualTo($other->expiry);
	}

	/**
	 * The last four digits, which is the most of a card number anything should ever show.
	 */
	public function lastFourDigits(): string
	{
		return substr($this->number, -4);
	}
}
