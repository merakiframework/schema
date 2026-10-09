<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Slot;

use RuntimeException;

/**
 * A source that answers from a list it was handed, for tests.
 *
 * Counts how often it was asked, which is what lets a test say the source was *not* consulted —
 * about a value that could not be read, or a field nobody filled in. A real source is a query, and
 * a query nobody needed is a cost nobody sees.
 *
 * Not readonly, for that counter. A field holding it is still sealed; it is the source that moves,
 * which is exactly the situation a real repository is in.
 */
final class FixedSource implements Source
{
	/** How many times the field has asked. */
	public int $asked = 0;

	/**
	 * @param list<string> $offered
	 */
	private function __construct(
		public readonly SourceId $id,
		public readonly Type $slotType,
		private readonly array $offered,
		private readonly ?Availability $answer,
		private readonly bool $breaks,
	) {
	}

	/**
	 * A date-time source with nothing on offer, for a test that needs a slot field and does not
	 * care what it accepts.
	 */
	public static function offeringNothing(): self
	{
		return self::offering(Type::DateTime);
	}

	/**
	 * Offers exactly these slots, and answers Unavailable for anything else.
	 */
	public static function offering(Type $slotType, string ...$slots): self
	{
		return self::named('fixed', $slotType, ...$slots);
	}

	public static function named(string $id, Type $slotType, string ...$slots): self
	{
		return new self(new SourceId($id), $slotType, array_values($slots), null, false);
	}

	/**
	 * Answers CannotCheck to everything — the adapter caught its own outage.
	 */
	public static function unreachable(Type $slotType): self
	{
		return new self(new SourceId('unreachable'), $slotType, [], Availability::CannotCheck, false);
	}

	/**
	 * Raises instead of answering — the adapter chose to fail the request rather than skip.
	 */
	public static function broken(Type $slotType): self
	{
		return new self(new SourceId('broken'), $slotType, [], null, true);
	}

	public function availabilityOf(Value $slot): Availability
	{
		$this->asked++;

		if ($this->breaks) {
			throw new RuntimeException('The slot source is down.');
		}

		if ($this->answer !== null) {
			return $this->answer;
		}

		// Compared as values rather than as strings, so a test offering `09:40` is offering the
		// slot somebody submits as `09:40:00` too.
		foreach ($this->offered as $offered) {
			if ((new Value($this->slotType, $offered))->equals($slot)) {
				return Availability::Available;
			}
		}

		return Availability::Unavailable;
	}
}
