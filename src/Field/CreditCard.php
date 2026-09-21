<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\ValueScope;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Field\CreditCard\Value;
use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;
use Brick\DateTime\Clock;
use Brick\DateTime\Instant;
use Brick\DateTime\Clock\SystemClock;
use Brick\DateTime\LocalDate;
use Brick\DateTime\TimeZone;
use SensitiveParameter;

/**
 * A payment card, held as one {@see Value}.
 *
 * A number, an expiry and a name are required; the security code is not, because plenty of flows
 * never ask for one — a stored card being re-authorised, or a terminal reading the chip.
 *
 * ### What this checks, and what it cannot
 *
 * The Luhn digit (ISO/IEC 7812-1), which every card number carries. A number failing it is not a
 * card number, so catching it here saves a round trip and gives the user a better message than a
 * processor's decline code. What it cannot tell you is whether the card *exists*, has funds, or has
 * been reported stolen — only the processor knows that, and this is deliberately not an attempt to
 * guess. It also does not identify the network: a leading `4` is probably a Visa, "probably" is not
 * something to validate against, and the ranges move.
 *
 * **Nothing here should be stored.** The value object lives for one request. Holding a real card
 * number is PCI DSS territory, and `#[SensitiveParameter]` keeps it out of a stack trace for that
 * reason — without keeping it from the consumer, who has a processor to talk to.
 *
 * ### Why it holds a clock
 *
 * "Has this expired" needs *now*, and a field is built once and shared across every request that
 * follows. So it holds a *source* of the instant rather than an instant: a {@see SystemClock} is
 * stateless and safe to share, whereas reading the date once into a property would be the
 * shared-mutable-state defect all over again — and would start rejecting valid cards the day after
 * the schema was built.
 *
 * @extends AtomicField<array<string, mixed>|Value|null>
 */
final readonly class CreditCard extends AtomicField
{
	/** ISO/IEC 7812 allows 8 to 19 digits; no issuer in use is below 13. */
	private const NUMBER_PATTERN = '/^\d{13,19}$/';

	/** Three digits, or four for American Express. */
	private const SECURITY_CODE_PATTERN = '/^\d{3,4}$/';

	/**
	 * How far ahead an expiry can plausibly be, in years.
	 *
	 * Issuers work to three to five, so twenty is far outside anything real and is meant to be:
	 * this catches a typo, not an unusual card. `2099` is a slipped keystroke, and telling someone
	 * their expiry looks wrong beats sending it to a processor that will decline it.
	 *
	 * A baseline rather than configuration, so it is not something an author can helpfully get
	 * wrong. If a card genuinely runs longer than this, the ceiling is the thing to revisit.
	 */
	private const MAX_YEARS_AHEAD = 20;

	/**
	 * Whether the card must not already have expired. Off by default: a form capturing a card for
	 * later reference is not the same as one about to charge it.
	 */
	public bool $mustExpireInFuture;

	/**
	 * Where *now* comes from.
	 *
	 * A *source* of the instant, never an instant. {@see SystemClock} is stateless and safe on a
	 * definition shared across requests, whereas reading the date once into a property would be
	 * the shared-state defect all over again — and would start rejecting valid cards the day after
	 * the schema was built.
	 *
	 * In the constructor rather than on {@see self::mustExpireInFuture()}, which is where it used
	 * to live. Two reasons it moved: `expiryWithinReach` needs one too and is not optional, so
	 * "only the rule that wants a clock holds one" stopped being true; and a schema can now
	 * declare a clock once for every field it builds, which needs somewhere on the field to put it
	 * that does not depend on which rules were switched on.
	 */
	public Clock $clock;

	public function __construct(
		public FieldName $name,
		?Clock $clock = null,
	) {
		parent::__construct();

		$this->mustExpireInFuture = false;
		$this->clock = $clock ?? new SystemClock();
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * Requires the card not to have expired already, judged against this field's clock.
	 *
	 * Off by default: a form capturing a card for later reference is not the same as one about to
	 * charge it.
	 */
	public function mustExpireInFuture(): static
	{
		return $this->with(['mustExpireInFuture' => true]);
	}

	/**
	 * What a rule may ask about this field: no string form, deliberately — see Field\CreditCard\Value.
	 *
	 * @see \Meraki\Schema\Rule\Matcher for the four sets and why a field declares one
	 */
	public function when(): Matcher\Basic
	{
		return new Matcher\Basic(ValueScope::of($this->name));
	}

	/**
	 * @param object|Value $value a record of the card's parts, or a {@see Value} already built
	 */
	protected function parse(#[SensitiveParameter] mixed $value): ?Value
	{
		if (!($value instanceof Value)) {
			$parts = self::recordIn($value);

			if ($parts === null) {
				return null;
			}

			$value = Value::fromInput($parts);
		}

		// A card with nothing in it is not a card. A *partly* filled one still is, so it gets past
		// here and the required-part constraints say which halves are missing.
		return $value->isEmpty() ? null : $value;
	}


	/**
	 * Each part's own question, named for the part it is about.
	 *
	 * The mandatory parts report here rather than through the shape check so a form can mark the
	 * field that is actually missing — a shape failure would only be able to say "that is not a
	 * card".
	 */
	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			new Constraint('numberFormat', $this->hasAWellFormedNumber(...), null, 'number'),
			new Constraint('numberChecksum', $this->passesLuhn(...), null, 'number'),
			new Constraint('expiryFormat', $this->hasAReadableExpiry(...), null, 'expiry'),
			// The bound is the instant it was judged against, so a message can say what "expired"
			// was measured from rather than only that it was. Per request, because that is what a
			// clock means.
			new Constraint(
				'expiryInFuture',
				$this->hasNotExpired(...),
				null,
				'expiry',
				fn(mixed $value): string => (string) $this->evaluatedAt(),
				timeRelative: true,
			),
			new Constraint(
				'expiryWithinReach',
				$this->expiresWithinReach(...),
				self::MAX_YEARS_AHEAD,
				'expiry',
				timeRelative: true,
			),
			new Constraint('namePresent', $this->namesAHolder(...), null, 'name'),
			new Constraint('securityCodeFormat', $this->hasAWellFormedSecurityCode(...), null, 'security_code'),
		);
	}

	/**
	 * Asks this field's clock what today is.
	 *
	 * A method rather than a property because the answer can change between two reads — which is
	 * the whole reason a clock is held instead of a date. Named to say it goes and finds out.
	 */
	public function determineToday(): LocalDate
	{
		return LocalDate::now(TimeZone::utc(), $this->clock);
	}

	protected function evaluatedAt(): Instant
	{
		return $this->clock->getTime();
	}

	private function hasAWellFormedNumber(Value $card): bool
	{
		return $card->number !== null && preg_match(self::NUMBER_PATTERN, $card->number) === 1;
	}

	/**
	 * Skipped when the number is not yet in a state the checksum can speak to — `numberFormat`
	 * reports that, and two failures for one mistake is one too many.
	 */
	private function passesLuhn(Value $card): ?bool
	{
		if (!$this->hasAWellFormedNumber($card)) {
			return null;
		}

		$sum = 0;
		$double = false;

		// Right to left: double every second digit, and cast a resulting 10-18 back down by
		// subtracting nine, which is the same as summing its two digits.
		for ($i = strlen((string) $card->number) - 1; $i >= 0; $i--) {
			$digit = (int) $card->number[$i];

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

	private function hasAReadableExpiry(Value $card): bool
	{
		return $card->expiry !== null;
	}

	/**
	 * Skipped unless asked for, and skipped when there is no expiry to judge — `expiryFormat`
	 * reports that instead.
	 */
	private function hasNotExpired(Value $card): ?bool
	{
		if (!$this->mustExpireInFuture || $card->expiry === null) {
			return null;
		}

		// The expiry is the last day of its month, so a card is good *through* that date.
		return $card->expiry->isAfterOrEqualTo($this->determineToday());
	}

	/**
	 * Whether the expiry is close enough to now to be a real card.
	 *
	 * Always asked, unlike {@see self::hasNotExpired()}: a field capturing a card for later still
	 * wants to know that `2099` was a typo. Skipped only when there is no expiry to judge, which
	 * `expiryFormat` reports instead.
	 */
	private function expiresWithinReach(Value $card): ?bool
	{
		if ($card->expiry === null) {
			return null;
		}

		return $card->expiry->isBeforeOrEqualTo($this->determineToday()->plusYears(self::MAX_YEARS_AHEAD));
	}

	private function namesAHolder(Value $card): bool
	{
		// `''` is a submitted empty string rather than an absent part, and either way it is not a
		// name — see Value::fromInput().
		return $card->name !== null && $card->name !== '';
	}

	/**
	 * Skipped when none was given: it is the one optional part.
	 */
	private function hasAWellFormedSecurityCode(Value $card): ?bool
	{
		if ($card->securityCode === null) {
			return null;
		}

		return preg_match(self::SECURITY_CODE_PATTERN, $card->securityCode) === 1;
	}
}
