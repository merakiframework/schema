<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\AtomicField;
use Meraki\Schema\Field\CreditCard\Value;
use Meraki\Schema\FieldName;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\ValueScope;
use Brick\DateTime\Clock;
use Brick\DateTime\Clock\SystemClock;
use Brick\DateTime\Instant;
use Brick\DateTime\LocalDate;
use Brick\DateTime\TimeZone;
use SensitiveParameter;

/**
 * A payment card, held as one {@see Value}.
 *
 * A number and an expiry are required. The cardholder's name and the security code are not,
 * because plenty of flows never ask for them — a stored card being re-authorised, a terminal
 * reading the chip, a processor that does not want the name. Sent, they still have to be readable.
 *
 * A flow that does ask for them says so: {@see self::makeNameRequired()} and
 * {@see self::makeSecurityCodeRequired()}. That is a demand this field makes rather than something
 * a card cannot be without, so it is a constraint — `nameRequired`, `securityCodeRequired` —
 * judged once there is a card, and a rule may switch it on for one request.
 *
 * ### A card is whole before it is judged
 *
 * Whether what arrived is a card at all — a number and an expiry there, the number shaped like one
 * and passing its checksum, every part that was sent readable — is decided by
 * {@see CreditCard\Input} before any constraint runs, and no configuration changes it. The two
 * constraints below are the ones that ask what day it is, so a default is never refused for an
 * expiry that was fine when the schema was written.
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
	 * Whether a card is refused without the cardholder's name. Off by default: a card is a card
	 * without one, and plenty of flows never ask.
	 */
	public bool $nameRequired;

	/**
	 * Whether a card is refused without its security code. Off by default, for the same reason.
	 */
	public bool $securityCodeRequired;

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

		$this->mustExpireInFuture = self::initially(false);
		$this->nameRequired = self::initially(false);
		$this->securityCodeRequired = self::initially(false);
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
	 * Refuses a card without the cardholder's name, as `nameRequired` against the name.
	 */
	public function makeNameRequired(): static
	{
		return $this->with(['nameRequired' => true]);
	}

	public function makeNameOptional(): static
	{
		return $this->with(['nameRequired' => false]);
	}

	/**
	 * Refuses a card without its security code, as `securityCodeRequired` against the code.
	 */
	public function makeSecurityCodeRequired(): static
	{
		return $this->with(['securityCodeRequired' => true]);
	}

	public function makeSecurityCodeOptional(): static
	{
		return $this->with(['securityCodeRequired' => false]);
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
	 * The record read part by part. Whether the parts make a card is the input's to say, and the
	 * lifecycle's to report — see {@see CreditCard\Input}.
	 *
	 * @param object|Value $value a record of the card's parts, or a {@see Value} already built
	 */
	protected function parse(#[SensitiveParameter] mixed $value): CreditCard\Input
	{
		if ($value instanceof Value) {
			return CreditCard\Input::of($value);
		}

		// An object is a record; an array is a list. A card has named parts, so it arrives as
		// the former — see Definition::recordIn().
		if (!is_object($value)) {
			throw MalformedValue::of(Value::class, 'a card is submitted as a record with a number, an expiry, a name and a security code');
		}

		return new CreditCard\Input($value);
	}

	/**
	 * The two questions that need a clock, and the two parts this field may demand. Whether there
	 * is a card at all is decided before these run, so each is handed a whole {@see Value}.
	 */
	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			// The bound is the instant it was judged against, so a message can say what "expired"
			// was measured from rather than only that it was. Per request, because that is what a
			// clock means.
			new Constraint(
				CreditCard\Check::ExpiryInFuture,
				$this->hasNotExpired(...),
				null,
				fn(mixed $value): string => (string) $this->evaluatedAt(),
				timeRelative: true,
			),
			new Constraint(
				CreditCard\Check::ExpiryWithinReach,
				$this->expiresWithinReach(...),
				self::MAX_YEARS_AHEAD,
				timeRelative: true,
			),
			new Constraint(CreditCard\Check::NameRequired, $this->hasAName(...), $this->nameRequired),
			new Constraint(CreditCard\Check::SecurityCodeRequired, $this->hasASecurityCode(...), $this->securityCodeRequired),
		);
	}

	protected static function declaredChecks(): array
	{
		return CreditCard\Check::cases();
	}

	protected static function declaredParts(): array
	{
		return CreditCard\Part::cases();
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

	/**
	 * Skipped unless asked for.
	 */
	private function hasNotExpired(Value $card): ?bool
	{
		if (!$this->mustExpireInFuture) {
			return null;
		}

		// The expiry is the last day of its month, so a card is good *through* that date.
		return $card->expiry->isAfterOrEqualTo($this->determineToday());
	}

	/**
	 * Skipped unless asked for. A name that was sent and holds no text never gets here — assembly
	 * reports it — so absent is the only way to fail.
	 */
	private function hasAName(Value $card): ?bool
	{
		return $this->nameRequired ? $card->name !== null : null;
	}

	/**
	 * Skipped unless asked for, and absent is the only way to fail, as for the name.
	 */
	private function hasASecurityCode(Value $card): ?bool
	{
		return $this->securityCodeRequired ? $card->securityCode !== null : null;
	}

	/**
	 * Whether the expiry is close enough to now to be a real card.
	 *
	 * Always asked, unlike {@see self::hasNotExpired()}: a field capturing a card for later still
	 * wants to know that `2099` was a typo.
	 */
	private function expiresWithinReach(Value $card): bool
	{
		return $card->expiry->isBeforeOrEqualTo($this->determineToday()->plusYears(self::MAX_YEARS_AHEAD));
	}
}
