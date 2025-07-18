<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;
use Brick\DateTime;
use Brick\DateTime\DateTimeException;

/**
 * @phpstan-import-type SerializedField from Field
 * @phpstan-type SerializedDuration = SerializedField&object{
 * 	type: 'duration',
 * 	value: string|null,
 * 	min: string,
 * 	max: string,
 * 	step: string
 * }
 * @extends AtomicField<string|null, SerializedDuration>
 */
final class Duration extends AtomicField
{
	public DateTime\Duration $min;

	public DateTime\Duration $max;

	public DateTime\Duration $step;

	public function __construct(
		Property\Name $name,
	) {
		parent::__construct(new Property\Type('duration', $this->validateType(...)), $name);

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

	/**
	 * @return SerializedDuration
	 */
	public function serialize(): object
	{
		return (object)[
			'type' => $this->type->value,
			'name' => $this->name->value,
			'optional' => $this->optional,
			'value' => $this->defaultValue->unwrap(),
			'fields' => [],
			'min' => $this->min->__toString(),
			'max' => $this->max->__toString(),
			'step' => $this->step->__toString(),
		];
	}

	/**
	 * @param SerializedDuration $serialized
	 */
	public static function deserialize(object $serialized, Field\Factory $fieldFactory): static
	{
		if ($serialized->type !== 'duration') {
			throw new \InvalidArgumentException('Invalid type for Duration field: ' . $serialized->type);
		}

		$field = new self(new Property\Name($serialized->name));
		$field->optional = $serialized->optional;
		$field->prefill($serialized->value);
		$field->minOf($serialized->min);
		$field->maxOf($serialized->max);
		$field->inIncrementsOf($serialized->step);

		return $field;
	}
}
