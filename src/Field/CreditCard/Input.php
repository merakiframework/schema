<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\CreditCard;

use Meraki\Schema\Exception\BrokenInputContract;
use Meraki\Schema\Field;
use Meraki\Schema\Field\Violation;
use Brick\DateTime\DateTimeException;
use Brick\DateTime\LocalDate;
use SensitiveParameter;

/**
 * A payment card as it was submitted, whether or not it is one yet: each part as read, and what
 * stops them making a {@see Value}.
 *
 * Seven things decide whether this is a card at all, and nothing else:
 *
 * | Code | The part | Wrong when |
 * | --- | --- | --- |
 * | `numberRequired` | number | it was not sent, or sent as `null` |
 * | `numberFormat` | number | it was sent and is not 13 to 19 digits |
 * | `numberChecksum` | number | it is 13 to 19 digits that fail the Luhn check |
 * | `expiryRequired` | expiry | it was not sent, or sent as `null` |
 * | `expiryFormat` | expiry | it was sent and is not a month or a date |
 * | `nameFormat` | name | it was sent and holds no text |
 * | `securityCodeFormat` | security code | it was sent and is not three or four digits |
 *
 * None of them reads the field's configuration or asks what day it is, which is what makes them
 * assembly rather than constraints. Whether the card has expired is the field's to say once there
 * is a card to say it about — it needs a clock, and an author decides whether it matters.
 *
 * The name and the security code are not essential: plenty of flows never ask for either, so a
 * card is a card without them. Sent, they still have to be readable. A field that does ask for one
 * says so with a constraint — `nameRequired`, `securityCodeRequired` — which another field may
 * decline, so it is judged once there is a card rather than here.
 *
 * Nothing here is ever written into a message. A violation carries a code, never what was typed,
 * so a card number cannot reach a log through one.
 */
final readonly class Input implements Field\Input
{
	/** ISO/IEC 7812 allows 8 to 19 digits; no issuer in use is below 13. */
	private const NUMBER_PATTERN = '/^\d{13,19}$/';

	/** Three digits, or four for American Express. */
	private const SECURITY_CODE_PATTERN = '/^\d{3,4}$/';

	/**
	 * The PAN with its grouping removed, once it is digits that pass Luhn; `null` until then.
	 *
	 * No `#[SensitiveParameter]`: the attribute targets parameters, and PHP refuses it on a
	 * property.
	 */
	public ?string $number;

	/** The last day of the stated month, once it could be read. */
	public ?LocalDate $expiry;

	/** The cardholder's name as printed, when one was given and holds text. */
	public ?string $name;

	/** The security code, when one was given and is three or four digits. */
	public ?string $securityCode;

	/** A card, once its number and expiry are sound and nothing sent is unreadable. */
	public ?Value $value;

	/** @var list<Violation> */
	public array $violations;

	/** @var list<Part> */
	public array $missingParts;

	/**
	 * Takes the record a field takes, so there is one answer to "what is a card here".
	 *
	 * A record with nothing in it is read like any other — the number and the expiry missing —
	 * though a field never hands one over: to a field it is nothing submitted.
	 *
	 * @param object{number?: string|null, expiry?: string|null, name?: string|null, security_code?: string|null} $card
	 * @throws BrokenInputContract if it carries a key a card does not have
	 */
	public function __construct(#[SensitiveParameter] object $card)
	{
		$parts = get_object_vars($card);
		$names = array_column(Part::cases(), 'value');
		$unknown = array_diff(array_keys($parts), $names);

		// Raised, not reported. Only the key *names* reach the message — never a value, on a
		// type where a value is a card number.
		if ($unknown !== []) {
			throw BrokenInputContract::recordHasKeysItDoesNotAccept(Value::class, array_values($unknown), $names);
		}

		$violations = [];
		$missing = [];
		$number = null;
		$expiry = null;

		if (($parts['number'] ?? null) === null) {
			$violations[] = new Violation(Check::NumberRequired, true);
			$missing[] = Part::Number;
		} else {
			[$number, $problem] = self::numberIn($parts['number']);
			$violations = $problem === null ? $violations : [...$violations, new Violation($problem)];
		}

		if (($parts['expiry'] ?? null) === null) {
			$violations[] = new Violation(Check::ExpiryRequired, true);
			$missing[] = Part::Expiry;
		} else {
			$expiry = self::expiryIn($parts['expiry']);
			$violations = $expiry === null ? [...$violations, new Violation(Check::ExpiryFormat)] : $violations;
		}

		[$name, $nameReadable] = self::textIn($parts['name'] ?? null);
		$violations = $nameReadable ? $violations : [...$violations, new Violation(Check::NameFormat)];

		[$securityCode, $codeReadable] = self::textIn($parts['security_code'] ?? null, self::SECURITY_CODE_PATTERN);
		$violations = $codeReadable ? $violations : [...$violations, new Violation(Check::SecurityCodeFormat)];

		$this->number = $number;
		$this->expiry = $expiry;
		$this->name = $name;
		$this->securityCode = $securityCode;
		$this->violations = $violations;
		$this->missingParts = $missing;
		$this->value = ($violations === [] && $number !== null && $expiry !== null)
			? new Value($number, $expiry, $name, $securityCode)
			: null;
	}

	/**
	 * The input a whole card would have been read from, so a field handed its own value reads it
	 * the way it reads anything else.
	 */
	public static function of(#[SensitiveParameter] Value $card): self
	{
		return new self((object) [
			'number' => $card->number,
			'expiry' => (string) $card->expiry,
			'name' => $card->name,
			'security_code' => $card->securityCode,
		]);
	}

	/**
	 * The number with its grouping removed, or the code saying what is wrong with it.
	 *
	 * The grouping is the one thing canonicalised: ISO/IEC 7812 says a PAN is digits, so the
	 * spaces people type it in are a display convention rather than part of the number. The
	 * checksum is asked only of something shaped like a card number — two failures for one
	 * mistake is one too many.
	 *
	 * @return array{?string, ?Check}
	 */
	private static function numberIn(#[SensitiveParameter] mixed $given): array
	{
		$number = is_string($given) ? preg_replace('/\s+/', '', $given) : null;

		if ($number === null || preg_match(self::NUMBER_PATTERN, $number) !== 1) {
			return [null, Check::NumberFormat];
		}

		return self::passesLuhn($number) ? [$number, null] : [null, Check::NumberChecksum];
	}

	/**
	 * The Luhn digit (ISO/IEC 7812-1), which every card number carries.
	 */
	private static function passesLuhn(#[SensitiveParameter] string $number): bool
	{
		$sum = 0;
		$double = false;

		// Right to left: double every second digit, and cast a resulting 10-18 back down by
		// subtracting nine, which is the same as summing its two digits.
		for ($i = strlen($number) - 1; $i >= 0; $i--) {
			$digit = (int) $number[$i];

			if ($double) {
				$digit *= 2;

				if ($digit > 9) {
					$digit -= 9;
				}
			}

			$sum += $digit;
			$double = !$double;
		}

		return $sum % 10 === 0;
	}

	/**
	 * The last day of the stated month, or `null` when there is no month to read.
	 *
	 * The last day rather than the first because a card expiring in `2026-09` is good until the end
	 * of September. Taking the first would reject a valid card for up to thirty days.
	 *
	 * Either spelling is read: `YYYY-MM`, which is what `<input type="month">` submits, or a full
	 * date.
	 */
	private static function expiryIn(mixed $given): ?LocalDate
	{
		if (!is_string($given)) {
			return null;
		}

		if (preg_match('/^(\d{4})-(\d{2})$/', $given, $parts) === 1) {
			try {
				return LocalDate::of((int) $parts[1], (int) $parts[2], 1)->plusMonths(1)->minusDays(1);
			} catch (DateTimeException) {
				return null;
			}
		}

		try {
			return LocalDate::parse($given);
		} catch (DateTimeException) {
			return null;
		}
	}

	/**
	 * An optional part as read: absent is fine, and sent has to be text — and, given a pattern,
	 * text that matches it. `''` was a decision somebody made, so a blank one is wrong rather
	 * than absent. Nothing is trimmed beyond that; repairing input is the port's job.
	 *
	 * @return array{?string, bool} the text, and whether what was sent was readable
	 */
	private static function textIn(#[SensitiveParameter] mixed $given, ?string $pattern = null): array
	{
		if ($given === null) {
			return [null, true];
		}

		if (!is_string($given) || trim($given) === '') {
			return [null, false];
		}

		return ($pattern === null || preg_match($pattern, $given) === 1) ? [$given, true] : [null, false];
	}

	/**
	 * The number and the security code are here because a part scope reads what the field
	 * holds, and withholding them would only send a caller to the properties instead. Nothing
	 * about a part scope makes a card safe to log — see the value's note on that.
	 *
	 * The expiry is an {@see Expiry}, which a rule can compare and order; a bare `LocalDate` it
	 * could do neither with.
	 *
	 * @return array<string, mixed>
	 */
	public function parts(): array
	{
		return [
			'number' => $this->number,
			'expiry' => $this->expiry === null ? null : new Expiry($this->expiry),
			'name' => $this->name,
			'security_code' => $this->securityCode,
		];
	}

	/**
	 * Read the way the parts were: a number without its grouping, and an expiry — `2026-09` or a
	 * full date — as the {@see Expiry} it would have been stored as. Everything else, and anything
	 * that cannot be read so, as written.
	 */
	public function canonicalPartValue(Field\Part $part, mixed $expected): mixed
	{
		if (!is_string($expected)) {
			return $expected;
		}

		if ($part === Part::Number) {
			return preg_replace('/\s+/', '', $expected);
		}

		$expiry = $part === Part::Expiry ? self::expiryIn($expected) : null;

		return $expiry === null ? $expected : new Expiry($expiry);
	}
}
