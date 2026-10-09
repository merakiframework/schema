<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Definition;
use Meraki\Schema\Exception\IncomparableValues;
use Meraki\Schema\Exception\InvalidConfiguration;
use Meraki\Schema\Exception\InvalidDefault;
use Meraki\Schema\Field\Slot\FixedSource;
use Meraki\Schema\Field\Slot\Type;
use Meraki\Schema\FieldName;
use Meraki\Schema\FieldTestCase;
use Meraki\Schema\ValidationStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

/**
 * A slot is one of the times a source is offering, and the source is asked rather than listed.
 *
 * Read top to bottom, these are the behaviours the field was designed against: it holds one type
 * of slot, it asks its source whether what was submitted is available, an outage is a skip and
 * not a failure, and the definition never asks the source anything.
 */
#[Group('field')]
#[CoversClass(Slot::class)]
#[CoversClass(Slot\Value::class)]
#[CoversClass(Slot\Type::class)]
#[CoversClass(Slot\Availability::class)]
final class SlotTest extends FieldTestCase
{
	public function createField(): Slot
	{
		return self::slotOffering(Type::DateTime, '2026-10-13T09:40');
	}

	private static function slotOffering(Type $type, string ...$slots): Slot
	{
		return new Slot(new FieldName('appointment'), FixedSource::offering($type, ...$slots));
	}

	// ── what it holds ───────────────────────────────────────────────────────

	#[Test]
	public function it_holds_the_type_of_slot_its_source_offers(): void
	{
		$field = new Slot(new FieldName('arrival'), FixedSource::offering(Type::Date));

		$this->assertSame(Type::Date, $field->slotType);
	}

	/** @return iterable<string, array{Type, string}> */
	public static function oneSlotOfEachType(): iterable
	{
		yield 'a day' => [Type::Date, '2026-10-13'];
		yield 'a date and time' => [Type::DateTime, '2026-10-13T09:40'];
		yield 'a time of day' => [Type::Time, '09:40'];
	}

	#[Test]
	#[DataProvider('oneSlotOfEachType')]
	public function it_reads_a_slot_of_its_own_type(Type $type, string $submitted): void
	{
		$result = self::slotOffering($type, $submitted)->validate($submitted);

		$this->assertShapePassed($result);
		$this->assertInstanceOf(Slot\Value::class, $result->value);
		$this->assertSame($type, $result->value->slotType);
		$this->assertSame($submitted, (string) $result->value);
	}

	/** @return iterable<string, array{Type, string}> */
	public static function slotsOfTheWrongType(): iterable
	{
		yield 'a date and time, where days are offered' => [Type::Date, '2026-10-13T09:40'];
		yield 'a time, where days are offered' => [Type::Date, '09:40'];
		yield 'a day, where dates and times are offered' => [Type::DateTime, '2026-10-13'];
		yield 'a time, where dates and times are offered' => [Type::DateTime, '09:40'];
		yield 'a date and time, where times are offered' => [Type::Time, '2026-10-13T09:40'];
		yield 'a day, where times are offered' => [Type::Time, '2026-10-13'];
	}

	/**
	 * Every value one field holds is the same type of slot, so a value of another type is not a
	 * slot this field can read — and the source is never asked about it.
	 */
	#[Test]
	#[DataProvider('slotsOfTheWrongType')]
	public function it_cannot_read_a_slot_of_another_type(Type $type, string $submitted): void
	{
		$source = FixedSource::offering($type);
		$field = new Slot(new FieldName('appointment'), $source);

		$result = $field->validate($submitted);

		$this->assertShapeUnreadable($result);
		$this->assertConstraintValidationResultSkipped('available', $result);
		$this->assertSame(0, $source->asked);
	}

	#[Test]
	public function a_slot_is_submitted_as_a_string(): void
	{
		$this->assertShapeUnreadable($this->createField()->validate(940));
	}

	#[Test]
	public function writing_the_seconds_out_names_the_same_slot(): void
	{
		$result = self::slotOffering(Type::DateTime, '2026-10-13T09:40')->validate('2026-10-13T09:40:00');

		$this->assertConstraintValidationResultPassed('available', $result);
	}

	// ── asking the source ───────────────────────────────────────────────────

	#[Test]
	public function an_offered_slot_is_available(): void
	{
		$result = self::slotOffering(Type::DateTime, '2026-10-13T09:40')->validate('2026-10-13T09:40');

		$this->assertConstraintValidationResultPassed('available', $result);
		$this->assertSame(ValidationStatus::Passed, $result->status);
	}

	#[Test]
	public function a_slot_the_source_does_not_offer_is_unavailable(): void
	{
		$result = self::slotOffering(Type::DateTime, '2026-10-13T09:40')->validate('2026-10-13T10:20');

		$this->assertConstraintValidationResultFailed('available', $result);
		$this->assertSame(ValidationStatus::Failed, $result->status);
	}

	/**
	 * "Available according to whom" is answered by the bound, so a message can say which list the
	 * slot was missing from.
	 */
	#[Test]
	public function the_verdict_names_the_source_it_asked(): void
	{
		$source = FixedSource::named('standard_consult', Type::DateTime);
		$field = new Slot(new FieldName('appointment'), $source);

		$verdict = $field->validate('2026-10-13T10:20')->forConstraint('available');

		$this->assertSame('standard_consult', $verdict->bound);
	}

	/**
	 * Nothing was learned about the slot, so the constraint is skipped rather than failed — and a
	 * field whose only constraint was skipped has *passed*. That is deliberate: the check at the
	 * form is advisory, and the booking is what refuses a slot that has gone.
	 */
	#[Test]
	public function a_source_that_cannot_check_skips_the_constraint(): void
	{
		$field = new Slot(new FieldName('appointment'), FixedSource::unreachable(Type::DateTime));

		$result = $field->validate('2026-10-13T09:40');

		$this->assertConstraintValidationResultSkipped('available', $result);
		$this->assertSame(ValidationStatus::Passed, $result->status);
	}

	/**
	 * The other choice an adapter has. Catching its own outage and answering CannotCheck is one; letting
	 * the exception through fails the request instead, and the field does not stand in the way.
	 */
	#[Test]
	public function an_exception_from_the_source_is_not_absorbed(): void
	{
		$field = new Slot(new FieldName('appointment'), FixedSource::broken(Type::DateTime));

		$this->expectException(RuntimeException::class);

		$field->validate('2026-10-13T09:40');
	}

	#[Test]
	public function the_source_is_not_asked_when_nothing_was_submitted(): void
	{
		$source = FixedSource::offering(Type::DateTime);
		$field = (new Slot(new FieldName('appointment'), $source))->makeOptional();

		$field->validate(null);

		$this->assertSame(0, $source->asked);
	}

	// ── defaults ────────────────────────────────────────────────────────────

	/**
	 * Whether a slot is available changes without the schema changing, so it cannot be settled
	 * where the default is written — and asking would put a query in the middle of building a
	 * schema.
	 */
	#[Test]
	public function a_default_is_not_checked_against_the_source_where_it_is_written(): void
	{
		$source = FixedSource::offering(Type::DateTime);

		$field = (new Slot(new FieldName('appointment'), $source))->defaultsTo('2026-10-13T09:40');

		$this->assertSame('2026-10-13T09:40', $field->defaultValue);
		$this->assertSame(0, $source->asked);
	}

	#[Test]
	public function a_default_is_checked_against_the_source_when_it_stands_in(): void
	{
		$field = self::slotOffering(Type::DateTime)->defaultsTo('2026-10-13T09:40');

		$this->assertConstraintValidationResultFailed('available', $field->validate(null));
	}

	#[Test]
	public function a_default_of_another_type_is_refused_where_it_is_written(): void
	{
		$this->expectException(InvalidDefault::class);

		self::slotOffering(Type::DateTime)->defaultsTo('09:40');
	}

	// ── changing the source ─────────────────────────────────────────────────

	#[Test]
	public function it_can_be_offered_by_another_source_of_the_same_type(): void
	{
		$field = self::slotOffering(Type::DateTime);
		$extended = FixedSource::named('extended_consult', Type::DateTime, '2026-10-13T13:00');

		$moved = $field->offeredBy($extended);

		$this->assertSame($extended, $moved->source);
		$this->assertConstraintValidationResultPassed('available', $moved->validate('2026-10-13T13:00'));
		$this->assertNotSame($extended, $field->source, 'The original was modified.');
	}

	/**
	 * All of a field's values are one type of slot, and a source of another type would break that
	 * from underneath the values already submitted against it.
	 */
	#[Test]
	public function it_refuses_a_source_offering_another_type_of_slot(): void
	{
		$this->expectException(InvalidConfiguration::class);

		self::slotOffering(Type::DateTime)->offeredBy(FixedSource::offering(Type::Time));
	}

	#[Test]
	public function a_rule_can_change_which_source_is_asked(): void
	{
		$schema = new Definition('booking');
		$standard = FixedSource::named('standard_consult', Type::DateTime, '2026-10-13T09:00');
		$extended = FixedSource::named('extended_consult', Type::DateTime, '2026-10-13T13:00');

		$schema->add(
			$service = $schema->createEnumField('service', ['standard_consult', 'extended_consult']),
			$appointment = $schema->createSlotField('appointment', $standard),
		);
		$schema->addRule(
			$service->when()->equals('extended_consult')->then($appointment->offeredBy($extended)),
		);

		$asExtended = $schema
			->validate((object) ['service' => 'extended_consult', 'appointment' => '2026-10-13T13:00'])
			->forField('appointment')->forConstraint('available');
		$asStandard = $schema
			->validate((object) ['service' => 'standard_consult', 'appointment' => '2026-10-13T13:00'])
			->forField('appointment')->forConstraint('available');

		$this->assertSame(ValidationStatus::Passed, $asExtended->status);
		$this->assertSame('extended_consult', $asExtended->bound);
		$this->assertSame(ValidationStatus::Failed, $asStandard->status);
		$this->assertSame('standard_consult', $asStandard->bound);
	}

	/**
	 * A slot is a point in time, so a rule can ask where it falls — the thing an enum of offered
	 * times could not do.
	 */
	#[Test]
	public function a_rule_can_ask_where_a_slot_falls(): void
	{
		$schema = new Definition('booking');

		$schema->add(
			$appointment = $schema->createSlotField(
				'appointment',
				FixedSource::offering(Type::DateTime, '2026-11-03T10:00', '2026-12-22T10:00'),
			),
			$deposit = $schema->createTextField('deposit_reference')->makeOptional(),
		);
		$schema->addRule(
			$appointment->when()->isBetween('2026-12-21T00:00', '2027-01-04T23:59')
				->then($deposit->makeRequired()),
		);

		$holidays = $schema->validate((object) ['appointment' => '2026-12-22T10:00']);
		$ordinary = $schema->validate((object) ['appointment' => '2026-11-03T10:00']);

		$this->assertFalse($holidays->forField('deposit_reference')->field->optional);
		$this->assertTrue($ordinary->forField('deposit_reference')->field->optional);
	}

	#[Test]
	public function a_schema_builds_one_from_a_source(): void
	{
		$source = FixedSource::offering(Type::Time);

		$field = (new Definition('booking'))->createSlotField('pickup', $source);

		$this->assertSame('pickup', (string) $field->name);
		$this->assertSame($source, $field->source);
		$this->assertSame(Type::Time, $field->slotType);
	}

	// ── the value ───────────────────────────────────────────────────────────

	#[Test]
	public function the_same_slot_read_twice_is_equal(): void
	{
		$first = new Slot\Value(Type::DateTime, '2026-10-13T09:40');
		$second = new Slot\Value(Type::DateTime, '2026-10-13T09:40:00');

		$this->assertTrue($first->equals($second));
		$this->assertTrue($first->compareTo($second)->isEqual());
	}

	/**
	 * Midnight on the 13th and the 13th are different slots: one is a time, the other a whole day.
	 */
	#[Test]
	public function slots_of_different_types_are_never_equal(): void
	{
		$day = new Slot\Value(Type::Date, '2026-10-13');
		$midnight = new Slot\Value(Type::DateTime, '2026-10-13T00:00');

		$this->assertFalse($day->equals($midnight));
		$this->assertFalse($midnight->equals($day));
	}

	#[Test]
	public function slots_are_ordered_within_a_type(): void
	{
		$earlier = new Slot\Value(Type::Time, '09:40');
		$later = new Slot\Value(Type::Time, '13:00');

		$this->assertTrue($earlier->compareTo($later)->isLess());
		$this->assertTrue($later->compareTo($earlier)->isGreater());
	}

	#[Test]
	public function slots_of_different_types_cannot_be_ordered(): void
	{
		$this->expectException(IncomparableValues::class);

		(new Slot\Value(Type::Date, '2026-10-13'))->compareTo(new Slot\Value(Type::Time, '09:40'));
	}
}
