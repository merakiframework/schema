<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;
use Brick\DateTime\DateTimeException;
use Brick\DateTime\Period;
use Brick\DateTime\LocalDate;

/**
 * @phpstan-import-type SerializedField from Field
 * @phpstan-type SerializedDate = SerializedField&object{
 * 	type: 'date',
 * 	value: string|null,
 * 	from: string,
 * 	until: string,
 * 	interval: string
 * }
 * @extends AtomicField<string|null, SerializedDate>
 */
final class Date extends AtomicField
{
	public LocalDate $from;
	public LocalDate $until;
	public Period $interval;

	public function __construct(
		Property\Name $name,
	) {
		parent::__construct(new Property\Type('date', $this->validateType(...)), $name);

		$this->from = LocalDate::min();
		$this->until = LocalDate::max();
		$this->interval = Period::ofDays(1);
	}

	/**
	 * This is inclusive of the date provided.
	 */
	public function from(string $date): self
	{
		$this->from = LocalDate::parse($date);

		return $this;
	}

	/**
	 * This is exclusive of the date provided.
	 */
	public function until(string $date): self
	{
		$this->until = LocalDate::parse($date);

		return $this;
	}

	/**
	 * This is inclusive of the date provided.
	 */
	public function to(string $date): self
	{
		$this->until = LocalDate::parse($date)->plusDays(1);

		return $this;
	}

	public function atIntervalsOf(string $date): self
	{
		$this->interval = Period::parse($date);

		return $this;
	}

	protected function cast(mixed $value): LocalDate
	{
		return LocalDate::parse($value);
	}

	protected function validateType(mixed $value): bool
	{
		if (!is_string($value)) {
			return false;
		}

		try {
			$date = $this->cast($value);
			return true;
		} catch (DateTimeException $e) {
			return false;
		}
	}

	protected function getConstraints(): array
	{
		return [
			'from' => $this->validateFrom(...),
			'until' => $this->validateUntil(...),
			'interval' => $this->validateInterval(...),
		];
	}

	private function validateFrom(mixed $value): bool
	{
		return $this->cast($value)->isAfterOrEqualTo($this->from);
	}

	private function validateUntil(mixed $value): bool
	{
		return $this->cast($value)->isBefore($this->until);
	}

	private function validateInterval(mixed $value): bool
	{
		$date = $this->cast($value);

		if ($date->isEqualTo($this->from)) {
			return true;
		}

		// Day-based intervals (including the P1D default): the number of days
		// from `from` must be a whole multiple of the interval.
		if ($this->interval->getYears() === 0 && $this->interval->getMonths() === 0) {
			$intervalDays = $this->interval->getDays();

			if ($intervalDays <= 0) {
				return false;
			}

			return $this->from->daysUntil($date) % $intervalDays === 0;
		}

		// Month/year based intervals: step from `from` until we land on or pass
		// the value.
		$cursor = $this->from;

		while ($cursor->isBeforeOrEqualTo($date)) {
			if ($cursor->isEqualTo($date)) {
				return true;
			}

			$cursor = $cursor->plusPeriod($this->interval);
		}

		return false;
	}

	/**
	 * @return SerializedDate
	 */
	public function serialize(): object
	{
		return (object)[
			'type' => $this->type->value,
			'name' => $this->name->value,
			'optional' => $this->optional,
			'value' => $this->defaultValue->unwrap(),
			'fields' => [],
			'from' => $this->from->__toString(),
			'until' => $this->until->__toString(),
			'interval' => $this->interval->__toString(),
		];
	}

	/**
	 * @param SerializedDate $serialized
	 */
	public static function deserialize(object $serialized, Field\Factory $fieldFactory): static
	{
		if ($serialized->type !== 'date') {
			throw new \InvalidArgumentException('Invalid type for Date field: ' . $serialized->type);
		}

		$field = new self(new Property\Name($serialized->name));
		$field->optional = $serialized->optional;
		$field->prefill($serialized->value);
		$field->from($serialized->from);
		$field->until($serialized->until);
		$field->atIntervalsOf($serialized->interval);

		return $field;
	}
}
