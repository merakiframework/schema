<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\AtomicField;
use Meraki\Schema\Exception\InvalidConfiguration;
use Meraki\Schema\Field\Slot\Availability;
use Meraki\Schema\Field\Slot\Source;
use Meraki\Schema\Field\Slot\Type;
use Meraki\Schema\Field\Slot\Value;
use Meraki\Schema\FieldName;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\ValueScope;

/**
 * One of the slots a source is offering: an appointment, a table, a night, a pickup time.
 *
 * ### The slots are asked about, never listed
 *
 * The obvious shape is an {@see Enum} of every offered start, and it stops working at the size a
 * booking platform actually is. Eighteen months of fifteen-minute slots for one practitioner is
 * around twelve thousand values. They change with every booking, so the definition could no longer
 * be built once and shared. And an enum's value is text, so no rule could ask whether a slot falls
 * in the holidays. Nothing needs the whole list anyway: validating a value needs one answer about
 * that value, and a picker needs the week on screen.
 *
 * So the field holds a {@see Source} and asks it about the one slot that was submitted. What a
 * definition carries is the source's id — what a port writes down, and what it finds the source by
 * when drawing a picker — so it stays the same size however far ahead bookings open. The offered
 * slots need not fall on any interval either: the source is the whole answer.
 *
 * ### Available is a constraint, not the shape
 *
 * Unlike {@see Enum}, where the list is the type and anything outside it is unreadable. "That is
 * not a date and time" and "that time is not available" are different sentences, and the second is
 * about a perfectly good date and time. So a value of the field's type always reads, and
 * `available` reports whether the source is offering it, with the source's id as its bound so a
 * message can say whose slots it was missing from.
 *
 * A source that cannot be reached answers {@see Availability::CannotCheck}, and the constraint is
 * skipped rather than failed: nothing was learned about the slot. A field whose constraints passed
 * or were skipped has passed, so an outage lets the form through. That is the intended trade. The
 * check here is advice, and the booking is what has to refuse a slot that has gone — whether it
 * went during an outage or in the second between drawing the form and submitting it. A source that
 * would rather fail the request throws, and nothing here catches it.
 *
 * ### One type of slot
 *
 * A slot is a day, a date and time, or a time of day ({@see Type}), and every value one field holds
 * is the same type. The source declares which, since it is what knows what it offers, and the field
 * takes the type from it rather than being told twice. A value of another type is unreadable, and
 * a source of another type is refused where it is handed over.
 *
 * Wall-clock time throughout, with no zone — see {@see Value} for why nothing is converted.
 *
 * ### Changing the source
 *
 * {@see self::offeredBy()} is the one configuration method, and rules are why it exists: a schema
 * offering a standard and an extended consultation switches source on the service chosen, with
 * `->then($appointment->offeredBy($extended))`. A source *per practitioner* is a different problem.
 * One rule per practitioner would put the practitioner list back into the definition, so that
 * waits on constraints that can read another field.
 *
 * @extends AtomicField<string|null>
 */
final readonly class Slot extends AtomicField
{
	/**
	 * What is asked whether a slot is on offer.
	 *
	 * A *source* of answers, never the answers — the distinction {@see CreditCard} draws for its
	 * clock, for the same reason. The field is shared across requests, so the source holds nothing
	 * about any one of them.
	 */
	public Source $source;

	/**
	 * The type of slot this field holds, taken from {@see self::$source}.
	 *
	 * Kept on the field as well, because a renderer choosing between a date, a date-time and a time
	 * picker reads it here, and because {@see self::offeredBy()} has to hold every later source to
	 * it.
	 */
	public Type $slotType;

	public function __construct(
		public FieldName $name,
		Source $source,
	) {
		parent::__construct();

		$this->source = $source;
		$this->slotType = $source->slotType;

		// Last: every property it reads must already be set.
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * Asks another source instead. The outcome a rule uses to switch source on another field's
	 * value.
	 *
	 * @throws InvalidConfiguration if the source offers another type of slot
	 */
	public function offeredBy(Source $source): static
	{
		if ($source->slotType !== $this->slotType) {
			throw InvalidConfiguration::sourceOffersAnotherTypeOfSlot(
				(string) $this->name,
				$this->slotType->value,
				(string) $source->id,
				$source->slotType->value,
			);
		}

		return $this->with(['source' => $source]);
	}

	/**
	 * What a rule may ask about this field: a slot is a point in time, so it can be ranked, and it
	 * reads back as text.
	 *
	 * @see \Meraki\Schema\Rule\Matcher for the four sets and why a field declares one
	 */
	public function when(): Matcher\OrderedText
	{
		return new Matcher\OrderedText(ValueScope::of($this->name));
	}

	protected function parse(mixed $value): Value
	{
		if ($value instanceof Value) {
			// Already read, though not necessarily as this field's type of slot.
			if ($value->slotType !== $this->slotType) {
				throw MalformedValue::of(
					Value::class,
					"a {$value->slotType->value} slot cannot stand in for a {$this->slotType->value} one",
				);
			}

			return $value;
		}

		if (!is_string($value)) {
			throw MalformedValue::of(Value::class, 'a slot is submitted as a string naming when it starts');
		}

		return new Value($this->slotType, $value);
	}

	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			// Time-relative because the answer changes without the field or the value changing,
			// which is what that flag records. It is also what exempts the constraint from the
			// check an authored default gets where it is written: asking there would settle a
			// question that has a different answer tomorrow, and put a query in the middle of
			// building a schema.
			new Constraint(
				'available',
				$this->isAvailable(...),
				(string) $this->source->id,
				timeRelative: true,
			),
		);
	}

	private function isAvailable(Value $slot): ?bool
	{
		return match ($this->source->availabilityOf($slot)) {
			Availability::Available => true,
			Availability::Unavailable => false,
			Availability::CannotCheck => null,
		};
	}
}
