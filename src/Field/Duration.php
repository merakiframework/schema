<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Property;
use Brick\DateTime;
use Brick\DateTime\DateTimeException;

final class Duration extends AtomicField
{
	public DateTime\Duration $min;

	public DateTime\Duration $max;

	public DateTime\Duration $step;

	public function __construct(
		Property\Name $name,
		Property\Value $value = null,
		Property\Value $defaultValue = null,
		bool $optional = false,
	) {
		parent::__construct(new Property\Type('duration'), $name, $value, $defaultValue, $optional);

		$this->min = DateTime\Duration::zero();
		$this->max = DateTime\Duration::ofDays(1);
		$this->step = DateTime\Duration::ofMinutes(1);
	}

	public function minOf(string $value): self
	{
		$this->min = $this->cast($value);

		return $this;
	}

	public function maxOf(string $value): self
	{
		$this->max = $this->cast($value);

		return $this;
	}

	public function inIncrementsOf(string $value): self
	{
		$this->step = $this->cast($value);

		return $this;
	}

	protected function validateType(mixed $value): bool
	{
		if (!is_string($value)) {
			return false;
		}

		try {
			$duration = $this->cast($value);
			return true;
		} catch (DateTimeException $e) {
			return false;
		}
	}

	protected function cast(mixed $value): DateTime\Duration
	{
		return DateTime\Duration::parse($value);
	}

	protected function getConstraints(): array
	{
		return [
			'min' => $this->validateMin(...),
			'max' => $this->validateMax(...),
			'step' => $this->validateStep(...),
		];
	}

	private function validateMin(mixed $value): bool
	{
		return $this->cast($value)->isGreaterThanOrEqualTo($this->min);
	}

	private function validateMax(mixed $value): bool
	{
		return $this->cast($value)->isLessThanOrEqualTo($this->max);
	}

	private function validateStep(mixed $value): bool
	{
		$value = $this->cast($value)->toNanos();
		$step = $this->step->toNanos();
		$min = $this->min->toNanos();

		if ($this->step->isZero()) {
			return false;
		}

		return ($value - $min) % $step === 0;
	}
}
