<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Slot;

use Meraki\Schema\Comparison\Comparable;
use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Comparison\Order;
use Meraki\Schema\Exception\IncomparableValues;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Field\ParsedValue;
use Brick\DateTime\DateTimeException;
use Brick\DateTime\LocalDate;
use Brick\DateTime\LocalDateTime;
use Brick\DateTime\LocalTime;

/**
 * One slot, named by when it starts.
 *
 * A slot is a stretch of time somebody can book, and what is submitted is its start: a day, a date
 * and time, or a time of day, according to {@see self::$slotType}. How long it lasts is the
 * source's business rather than the submitter's, so it is not here.
 *
 * ### Wall-clock time, with no zone
 *
 * A slot at 09:40 is at 09:40 where it happens, which is what the person booking needs to know and
 * what `<input type="datetime-local">` submits. Nothing is converted on the way in, so a rule
 * bound written as `2026-12-21T00:00` means midnight where the slot is. Turning a slot into an
 * instant is for whoever needs one — a reminder, a video link — at the moment they need it, with
 * the zone rules in force then. Converting to UTC up front would fix today's offset onto a booking
 * eighteen months out.
 *
 * ### Equality and order are within a type
 *
 * Midnight on the 13th is not the 13th: one is a time, the other a whole day. So two slots of
 * different types are never equal, and ordering them raises rather than guessing — the same
 * contract {@see \Meraki\Schema\Field\Money\Value} keeps across currencies.
 */
final readonly class Value implements ParsedValue, Comparable
{
	/** Which of the three it is. Every value one field holds has the same type. */
	public Type $slotType;

	/**
	 * When the slot starts: a `LocalDate` for {@see Type::Date}, a `LocalDateTime` for
	 * {@see Type::DateTime}, and a `LocalTime` for {@see Type::Time}.
	 */
	public LocalDate|LocalDateTime|LocalTime $start;

	/**
	 * @throws MalformedValue if the string is not the start of this type of slot
	 */
	public function __construct(Type $slotType, string $start)
	{
		$this->slotType = $slotType;

		try {
			$this->start = match ($slotType) {
				Type::Date => LocalDate::parse($start),
				Type::DateTime => LocalDateTime::parse($start),
				Type::Time => LocalTime::parse($start),
			};
		} catch (DateTimeException) {
			throw MalformedValue::of(self::class, sprintf('"%s" is not %s', $start, match ($slotType) {
				Type::Date => 'a date, as YYYY-MM-DD',
				Type::DateTime => 'a date and time, as YYYY-MM-DDTHH:MM',
				Type::Time => 'a time, as HH:MM or HH:MM:SS',
			}));
		}
	}

	public function equals(Equality $other): bool
	{
		return $other instanceof self
			&& $this->slotType === $other->slotType
			&& $this->orderAgainst($other) === 0;
	}

	/**
	 * @throws IncomparableValues if the other value is not a slot, or is another type of slot
	 */
	public function compareTo(Comparable $other): Order
	{
		if (!$other instanceof self) {
			throw IncomparableValues::slots();
		}

		return Order::of($this->orderAgainst($other));
	}

	public function __toString(): string
	{
		return (string) $this->start;
	}

	/**
	 * Brick's three types share no comparison method, so each is asked as itself. A start's class
	 * follows its slot type, which is why a pair that matches none of these is two types of slot.
	 */
	private function orderAgainst(self $other): int
	{
		$mine = $this->start;
		$theirs = $other->start;

		return match (true) {
			$mine instanceof LocalDate && $theirs instanceof LocalDate => $mine->compareTo($theirs),
			$mine instanceof LocalDateTime && $theirs instanceof LocalDateTime => $mine->compareTo($theirs),
			$mine instanceof LocalTime && $theirs instanceof LocalTime => $mine->compareTo($theirs),
			default => throw IncomparableValues::slotsOfDifferentTypes(
				$this->slotType->value,
				$other->slotType->value,
			),
		};
	}
}
