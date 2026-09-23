<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\CreditCard;
use Meraki\Schema\Field\CreditCard\Value;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\FieldName;
use Meraki\Schema\FieldTestCase;
use Brick\DateTime\Clock\FixedClock;
use Brick\DateTime\Clock\SystemClock;
use Meraki\Schema\Facade;
use Brick\DateTime\LocalDate;
use Brick\DateTime\LocalTime;
use Brick\DateTime\TimeZone;
use ReflectionMethod;
use SensitiveParameter;
use Stringable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[Group('field')]
#[CoversClass(CreditCard::class)]
#[CoversClass(Value::class)]
final class CreditCardTest extends FieldTestCase
{
	/** A date that is neither the start nor the end of its month, so off-by-one shows. */
	private const TODAY = '2026-09-12';

	public function createField(): CreditCard
	{
		// Pinned, so nothing here depends on when the suite runs. Every card field holds a clock —
		// `expiryWithinReach` asks the calendar whether or not expiry is being enforced.
		return new CreditCard(new FieldName('card'), self::clockAt(self::TODAY));
	}

	/** A field that also refuses a card that has already expired. */
	private function expiring(): CreditCard
	{
		return $this->createField()->mustExpireInFuture();
	}

	private static function clockAt(string $date): FixedClock
	{
		return new FixedClock(
			LocalDate::parse($date)->atTime(LocalTime::midnight())->atTimeZone(TimeZone::utc())->getInstant(),
		);
	}

	/** @return array<string, string> */
	private static function card(array $overrides = [], string ...$without): array
	{
		return array_diff_key($overrides + [
			'number' => '4242424242424242',
			'expiry' => '2027-01',
			'name' => 'J Bloggs',
		], array_flip($without));
	}

	// ── what a card is ────────────────────────────────────────────────────────────────────

	#[Test]
	public function it_holds_the_whole_card_as_one_value(): void
	{
		$value = $this->createField()->resolve((object) self::card())->value;

		$this->assertInstanceOf(Value::class, $value);
		$this->assertSame('4242424242424242', $value->number);
		$this->assertSame('J Bloggs', $value->name);
	}

	#[Test]
	public function a_number_may_be_typed_in_groups(): void
	{
		// People type cards in fours, so the spacing is stripped rather than rejected.
		$value = $this->createField()->resolve((object) self::card(['number' => '4242 4242 4242 4242']))->value;

		$this->assertSame('4242424242424242', $value->number);
	}

	#[Test]
	public function a_complete_card_passes(): void
	{
		$this->assertFalse($this->createField()->validate((object) self::card())->anyFailed());
	}

	#[Test]
	public function each_constraint_names_the_part_it_is_about(): void
	{
		$expected = [
			'numberFormat' => 'number',
			'numberChecksum' => 'number',
			'expiryFormat' => 'expiry',
			'expiryInFuture' => 'expiry',
			'expiryWithinReach' => 'expiry',
			'namePresent' => 'name',
			'securityCodeFormat' => 'security_code',
		];

		foreach ($this->createField()->constraints as $constraint) {
			$this->assertSame($expected[$constraint->name], $constraint->part, $constraint->name);
		}
	}

	#[Test]
	public function a_card_with_nothing_in_it_could_not_be_read_as_a_card(): void
	{
		// A composite with no parts at all is not a half-filled one; it is not one. So it fails the
		// shape rather than every required part in turn, which says the real thing once instead of
		// three times.
		$result = $this->createField()->validate((object) []);

		$this->assertShapeFailed($result);
		$this->assertConstraintValidationResultSkipped('numberFormat', $result);
	}

	#[Test]
	public function an_empty_card_cannot_be_built_at_all(): void
	{
		// Stronger than it used to be. This asserted that an empty Value read the same way as an
		// empty submission; now there is no empty Value to read, because the invariant moved into
		// the constructor. Both routes still agree — they agree by refusing.
		$this->assertShapeFailed($this->createField()->validate((object) []));

		$this->expectException(MalformedValue::class);

		Value::of();
	}

	#[Test]
	public function a_partly_filled_card_is_still_a_card(): void
	{
		// Which is where the per-part constraints earn their keep: something was entered, so the
		// report says which halves are missing rather than rejecting the lot.
		$result = $this->createField()->validate((object) self::card(without: 'name'));

		$this->assertShapePassed($result);
		$this->assertConstraintValidationResultFailed('namePresent', $result);
	}

	#[Test]
	public function nothing_at_all_is_absent(): void
	{
		// Null is the only absent card: the field was never filled in, which is a different report
		// from filling it in wrongly.
		$this->assertShapeFailed($this->createField()->validate(null));
	}

	#[Test]
	public function a_card_knows_whether_it_holds_the_three(): void
	{
		$this->assertTrue($this->createField()->resolve((object) self::card())->value->isComplete());
		$this->assertFalse((new Value((object) self::card(without: 'name')))->isComplete());
		$this->assertFalse(Value::of(number: '4242424242424242')->isComplete());
		// The security code is the one part a card can do without.
		$this->assertTrue((new Value((object) self::card()))->isComplete());
		$this->assertTrue((new Value((object) self::card(['security_code' => '123'])))->isComplete());
	}

	// ── the three required parts ──────────────────────────────────────────────────────────

	#[Test]
	#[DataProvider('requiredParts')]
	public function a_missing_required_part_is_reported_against_that_part(string $missing, string $constraint): void
	{
		// Reported here rather than as a shape failure so a form can mark the field that is
		// actually missing — "that is not a card" could not say which.
		$failed = $this->createField()->validate((object) self::card(without: $missing))->forConstraint($constraint);

		$this->assertTrue($failed->failed(), $constraint);
		$this->assertSame($missing, $failed->part);
	}

	/** @return array<string, array{string, string}> */
	public static function requiredParts(): array
	{
		return [
			'a number' => ['number', 'numberFormat'],
			'an expiry' => ['expiry', 'expiryFormat'],
			'a name' => ['name', 'namePresent'],
		];
	}

	#[Test]
	public function the_security_code_is_the_one_optional_part(): void
	{
		$result = $this->createField()->validate((object) self::card());

		$this->assertConstraintValidationResultSkipped('securityCodeFormat', $result);
		$this->assertFalse($result->anyFailed());
	}

	#[Test]
	#[DataProvider('securityCodes')]
	public function a_security_code_is_checked_once_given(string $code, bool $valid): void
	{
		$result = $this->createField()->validate((object) self::card(['security_code' => $code]));

		$this->assertSame(
			$valid,
			$result->forConstraint('securityCodeFormat')->passed(),
			"security code '{$code}'",
		);
	}

	/** @return array<string, array{string, bool}> */
	public static function securityCodes(): array
	{
		return [
			'three digits' => ['123', true],
			'four digits, as American Express uses' => ['1234', true],
			'two digits' => ['12', false],
			'five digits' => ['12345', false],
			'letters' => ['abc', false],
		];
	}

	// ── the number ────────────────────────────────────────────────────────────────────────

	#[Test]
	public function it_reports_a_number_that_fails_its_check_digit(): void
	{
		// Every card number carries a Luhn digit, so one that fails it is not a card number. No
		// processor would accept it, and catching it here saves a round trip.
		$this->assertConstraintValidationResultFailed(
			'numberChecksum',
			$this->createField()->validate((object) self::card(['number' => '4242424242424241'])),
		);
	}

	#[Test]
	#[DataProvider('badlyFormedNumbers')]
	public function a_number_that_is_not_digits_of_the_right_length_reports_once(string $number): void
	{
		// The checksum is skipped rather than also failing: two failures for one mistake is one
		// too many.
		$result = $this->createField()->validate((object) self::card(['number' => $number]));

		$this->assertConstraintValidationResultFailed('numberFormat', $result);
		$this->assertConstraintValidationResultSkipped('numberChecksum', $result);
	}

	/** @return array<string, array{string}> */
	public static function badlyFormedNumbers(): array
	{
		return [
			'too short' => ['424242'],
			'too long' => ['42424242424242424242'],
			'letters' => ['4242abcd4242efgh'],
		];
	}

	// ── expiry ────────────────────────────────────────────────────────────────────────────

	#[Test]
	public function an_expiry_month_runs_to_the_end_of_that_month(): void
	{
		// A card expiring 2026-09 is good until the 30th. Taking the first of the month would
		// reject a valid card for up to thirty days.
		$value = $this->createField()->resolve((object) self::card(['expiry' => '2026-09']))->value;

		$this->assertSame('2026-09-30', (string) $value->expiry);
	}

	#[Test]
	#[DataProvider('expiries')]
	public function it_judges_expiry_against_its_clock(string $expiry, bool $stillValid): void
	{
		$this->assertSame(
			$stillValid,
			$this->expiring()->validate((object) self::card(['expiry' => $expiry]))->forConstraint('expiryInFuture')->passed(),
			"expiry {$expiry} against " . self::TODAY,
		);
	}

	/** @return array<string, array{string, bool}> */
	public static function expiries(): array
	{
		return [
			'this month' => ['2026-09', true],
			'last month' => ['2026-08', false],
			'next month' => ['2026-10', true],
			'the last day of this month' => ['2026-09-30', true],
			// A full date is taken literally rather than widened to its month's end: if the author
			// was that precise, they meant it.
			'an exact date already past' => ['2026-09-01', false],
			'today exactly' => [self::TODAY, true],
		];
	}

	#[Test]
	public function expiry_is_not_checked_unless_it_was_asked_for(): void
	{
		// A form capturing a card for later reference is not one about to charge it.
		$this->assertConstraintValidationResultSkipped(
			'expiryInFuture',
			$this->createField()->validate((object) self::card(['expiry' => '2020-01'])),
		);
	}

	#[Test]
	public function an_unreadable_expiry_reports_once(): void
	{
		$result = $this->expiring()->validate((object) self::card(['expiry' => 'soon']));

		$this->assertConstraintValidationResultFailed('expiryFormat', $result);
		$this->assertConstraintValidationResultSkipped('expiryInFuture', $result);
	}

	#[Test]
	public function it_holds_a_source_of_the_instant_rather_than_an_instant(): void
	{
		// Reading the date once into a property would start rejecting valid cards the day after the
		// schema was built, and would be shared mutable state besides.
		$field = $this->expiring();

		$this->assertSame(self::TODAY, (string) $field->determineToday());
		$this->assertSame(self::TODAY, (string) $field->determineToday(), 'a clock is consulted, not cached');
	}

	#[Test]
	public function every_card_field_holds_a_clock(): void
	{
		// The clock used to arrive with `mustExpireInFuture()`, on the argument that a card
		// captured for later reference has no business knowing the time. That stopped being true
		// when `expiryWithinReach` arrived: catching `2099` as a typo is not optional, and it is
		// a question about the calendar like any other.
		//
		// A field given none falls back to a SystemClock, which is stateless and therefore safe
		// on a definition shared across requests.
		$field = new CreditCard(new FieldName('card'));

		$this->assertInstanceOf(SystemClock::class, $field->clock);
	}

	#[Test]
	public function a_schema_can_declare_the_clock_instead(): void
	{
		// Built once for every field the schema makes, the same shape as `for()`.
		$schema = new Facade('checkout', clock: self::clockAt(self::TODAY));

		$this->assertSame(self::TODAY, (string) $schema->createCreditCardField('card')->determineToday());
	}

	#[Test]
	public function it_falls_back_to_the_system_clock(): void
	{
		// The default exists so that the common case needs no ceremony. Asserting only that there is
		// a real date and that a long-past card fails against it, since the real clock moves.
		$field = $this->createField()->mustExpireInFuture();

		$this->assertNotNull($field->determineToday());
		$this->assertFalse($field->validate((object) self::card(['expiry' => '2020-01']))->forConstraint('expiryInFuture')->passed());
	}

	// ── a card number must not leak ───────────────────────────────────────────────────────

	#[Test]
	public function it_keeps_the_number_the_consumer_will_have_to_send(): void
	{
		// Masking here would only mean whatever talks to the processor reaching past this object for
		// the real thing. Display is what lastFourDigits() is for.
		$value = $this->createField()->resolve((object) self::card())->value;

		$this->assertSame('4242424242424242', $value->number);
		$this->assertSame('4242', $value->lastFourDigits());
		$this->assertNull(Value::of(name: 'Kim Nguyen')->lastFourDigits());
	}

	#[Test]
	public function it_cannot_be_printed_by_accident(): void
	{
		// No __toString(), deliberately: interpolation is exactly how a card number reaches a log,
		// and a masking one would still invite the habit.
		$value = $this->createField()->resolve((object) self::card())->value;

		$this->assertNotInstanceOf(Stringable::class, $value);
		$this->assertFalse(method_exists($value, '__toString'));
	}

	#[Test]
	public function the_secret_parts_are_marked_sensitive(): void
	{
		// The guard that replaces masking. A declaration test rather than a behavioural one on
		// purpose: whether an argument reaches a trace depends on zend.exception_ignore_args, which
		// is the host's setting and not ours — so the attribute being present is the part we own.
		$sensitive = static function (string $method, int $at): bool {
			$parameter = (new ReflectionMethod(Value::class, $method))->getParameters()[$at];

			return $parameter->getAttributes(SensitiveParameter::class) !== [];
		};

		$this->assertTrue($sensitive('__construct', 0), 'the submitted record holds both');
		$this->assertTrue($sensitive('of', 0), '$number');
		$this->assertTrue($sensitive('of', 3), '$securityCode');
		$this->assertFalse($sensitive('of', 2), '$name is not a secret');
	}

	#[Test]
	public function configuring_it_leaves_the_original_alone(): void
	{
		$field = $this->createField();
		$strict = $field->mustExpireInFuture();

		$this->assertNotSame($field, $strict);
		$this->assertFalse($field->mustExpireInFuture);
		$this->assertTrue($strict->mustExpireInFuture);
	}

	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$this->assertNull($this->createField()->defaultValue);
	}
}
