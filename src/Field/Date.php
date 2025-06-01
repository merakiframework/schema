<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Property;
use Brick\DateTime\DateTimeException;
use Brick\DateTime\Period;
use Brick\DateTime\LocalDate;

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
		$period = Period::between($this->from, $this->cast($value));
		return $period->isEqualTo($this->interval) || $period->isZero();
	}
}
