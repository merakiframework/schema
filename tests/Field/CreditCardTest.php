<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Definition;
use Meraki\Schema\Exception\InvalidDefault;
use Meraki\Schema\Field\CreditCard\Check;
use Meraki\Schema\Field\CreditCard\Input;
use Meraki\Schema\Field\CreditCard\Part;
use Meraki\Schema\Field\CreditCard\Value;
use Meraki\Schema\FieldName;
use Meraki\Schema\FieldTestCase;
use Brick\DateTime\Clock\FixedClock;
use Brick\DateTime\Clock\SystemClock;
use Brick\DateTime\LocalDate;
use Brick\DateTime\LocalTime;
use Brick\DateTime\TimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use SensitiveParameter;
use Stringable;

#[Group('field')]
#[CoversClass(CreditCard::class)]
#[CoversClass(Input::class)]
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
	public function every_code_names_the_part_it_is_about(): void
	{
		$expected = [
			'numberRequired' => 'number',
			'expiryRequired' => 'expiry',
			'numberFormat' => 'number',
			'numberChecksum' => 'number',
			'expiryFormat' => 'expiry',
			'nameFormat' => 'name',
			'securityCodeFormat' => 'security_code',
			'expiryInFuture' => 'expiry',
			'expiryWithinReach' => 'expiry',
		];

		$this->assertSame(array_keys($expected), array_column($this->createField()->checks, 'value'));

		foreach ($this->createField()->checks as $check) {
			$this->assertSame($expected[$check->value], $check->part()->value, $check->value);
		}
	}

	#[Test]
	#[DataProvider('noCard')]
	public function a_card_with_nothing_in_it_was_not_submitted(?object $given): void
	{
		// Every box left empty is the field never filled in, which is a different report from
		// filling it in wrongly: missing, once, rather than a required part at a time.
		$result = $this->createField()->validate($given);

		$this->assertShapeMissing($result);
		$this->assertConstraintValidationResultSkipped('expiryWithinReach', $result);
	}

	/** @return array<string, array{?object}> */
	public static function noCard(): array
	{
		return [
			'nothing at all' => [null],
			'an empty record' => [(object) []],
			'every part null' => [(object) ['number' => null, 'expiry' => null, 'name' => null, 'security_code' => null]],
		];
	}

	#[Test]
	public function a_card_is_a_number_and_an_expiry(): void
	{
		$this->assertSame([Part::Number, Part::Expiry], $this->createField()->essentialParts);

		// Neither is ever null on a value, and the constructor still guards the number.
		$value = new Value('4242424242424242', LocalDate::parse('2027-01-31'));

		$this->assertNull($value->name);
		$this->assertNull($value->securityCode);

		$this->expectException(MalformedValue::class);

		new Value('4242', LocalDate::parse('2027-01-31'));
	}

	#[Test]
	public function a_card_written_by_hand_is_read_the_way_a_form_is(): void
	{
		$this->assertSame('4242424242424242', Value::of('4242 4242 4242 4242', '2027-01')->number);

		// The codes only: a message about a card must never carry what was typed.
		$this->expectException(MalformedValue::class);
		$this->expectExceptionMessage('it does not make a card: expiryFormat');

		Value::of('4242424242424242', 'soon');
	}

	// ── the parts a card cannot be without ────────────────────────────────────────────────

	/**
	 * Reported against the part rather than as a shape failure, so a form can mark the field
	 * that is actually missing — "that is not a card" could not say which.
	 */
	#[Test]
	#[DataProvider('requiredParts')]
	public function a_missing_number_or_expiry_is_reported_against_that_part(string $missing, Check $code): void
	{
		$result = $this->expiring()->validate((object) self::card(without: $missing));

		$this->assertIncompleteWith([$code], $result);
		$this->assertSame([Part::from($missing)], $result->missingParts);
	}

	/** @return array<string, array{string, Check}> */
	public static function requiredParts(): array
	{
		return [
			'a number' => ['number', Check::NumberRequired],
			'an expiry' => ['expiry', Check::ExpiryRequired],
		];
	}

	#[Test]
	public function the_name_and_the_security_code_are_optional(): void
	{
		// Plenty of flows never ask for either: a stored card being re-authorised, a terminal
		// reading the chip, a processor that does not want the name.
		$result = $this->createField()->validate((object) self::card(without: 'name'));

		$this->assertFalse($result->anyFailed());
		$this->assertNull($result->value?->name);
	}

	#[Test]
	#[DataProvider('securityCodes')]
	public function a_security_code_is_checked_once_given(string $code, bool $valid): void
	{
		$result = $this->createField()->validate((object) self::card(['security_code' => $code]));

		if ($valid) {
			$this->assertFalse($result->anyFailed(), "security code '{$code}'");
		} else {
			$this->assertIncompleteWith([Check::SecurityCodeFormat], $result);
		}
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
			'blank' => ['', false],
		];
	}

	#[Test]
	public function a_name_that_was_sent_has_to_hold_text(): void
	{
		// Optional is not the same as anything goes: `''` was a decision somebody made.
		$this->assertIncompleteWith([Check::NameFormat], $this->createField()->validate((object) self::card(['name' => ''])));
	}

	#[Test]
	public function every_part_in_the_way_is_reported_at_once(): void
	{
		$result = $this->createField()->validate((object) ['number' => '4242', 'expiry' => 'soon', 'security_code' => 'abc']);

		$this->assertIncompleteWith([Check::NumberFormat, Check::ExpiryFormat, Check::SecurityCodeFormat], $result);
		$this->assertSame([], $result->missingParts);
	}

	#[Test]
	public function a_default_that_is_not_a_whole_card_is_refused_where_it_is_written(): void
	{
		$this->expectException(InvalidDefault::class);
		$this->expectExceptionMessage('The default for "card" does not make a whole value: "expiryRequired" on its expiry.');

		$this->createField()->defaultsTo((object) ['number' => '4242424242424242']);
	}

	// ── the number ────────────────────────────────────────────────────────────────────────

	#[Test]
	public function it_reports_a_number_that_fails_its_check_digit(): void
	{
		// Every card number carries a Luhn digit, so one that fails it is not a card number. No
		// processor would accept it, and catching it here saves a round trip.
		$this->assertIncompleteWith(
			[Check::NumberChecksum],
			$this->createField()->validate((object) self::card(['number' => '4242424242424241'])),
		);
	}

	#[Test]
	#[DataProvider('badlyFormedNumbers')]
	public function a_number_that_is_not_digits_of_the_right_length_reports_once(string $number): void
	{
		// The checksum is not asked as well: two failures for one mistake is one too many.
		$this->assertIncompleteWith(
			[Check::NumberFormat],
			$this->createField()->validate((object) self::card(['number' => $number])),
		);
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
		// An expiry that was *given* and cannot be read is wrong rather than missing, and it is
		// reported against the expiry — the box a form should mark. Nothing asks whether it has
		// passed, because there is no date to ask about.
		$this->assertIncompleteWith(
			[Check::ExpiryFormat],
			$this->expiring()->validate((object) self::card(['expiry' => 'soon'])),
		);
	}

	#[Test]
	public function a_default_is_never_refused_for_the_date(): void
	{
		// Whether a card has expired changes without the schema changing, so it is judged per
		// request — a default that was fine when the schema was written must not start throwing
		// at boot years later.
		$field = $this->expiring()->defaultsTo((object) self::card(['expiry' => '2020-01']));

		$this->assertConstraintValidationResultFailed('expiryInFuture', $field->validate(null));
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
		$schema = new Definition('checkout', clock: self::clockAt(self::TODAY));

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
		$sensitive = static function (string $class, string $method, int $at): bool {
			$parameter = (new ReflectionMethod($class, $method))->getParameters()[$at];

			return $parameter->getAttributes(SensitiveParameter::class) !== [];
		};

		$this->assertTrue($sensitive(Input::class, '__construct', 0), 'the submitted record holds both');
		$this->assertTrue($sensitive(Value::class, '__construct', 0), '$number');
		$this->assertTrue($sensitive(Value::class, '__construct', 3), '$securityCode');
		$this->assertTrue($sensitive(Value::class, 'of', 0), '$number');
		$this->assertTrue($sensitive(Value::class, 'of', 3), '$securityCode');
		$this->assertFalse($sensitive(Value::class, 'of', 2), '$name is not a secret');
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
