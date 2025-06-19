<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;
use InvalidArgumentException;

/**
 * @phpstan-import-type SerializedField from Field
 * @phpstan-type SerializedUri = SerializedField&object{
 * 	type: 'uri',
 * 	min: int,
 * 	max: int
 * }
 * @extends AtomicField<string|null, SerializedUri>
 */
final class Uri extends AtomicField
{
	private const PATTERN = '~^
		(?:([^:/\?\#]+):)?	# scheme
		(?://([^/\?\#]*))?	# authority
		([^\?\#]*)			# path
		(?:\?([^\#]*))?		# query
		(?:\#(.*))?			# fragment
	$~x';

	public int $min = 0;

	public int $max = PHP_INT_MAX;

	public function __construct(
		Property\Name $name,
	) {
		parent::__construct(new Property\Type('uri', $this->validateType(...)), $name);
	}

	public function minLengthOf(int $minChars): self
	{
		if ($minChars < 0) {
			throw new InvalidArgumentException('Minimum length must be a positive integer.');
		}

		if ($minChars > $this->max) {
			throw new InvalidArgumentException('Minimum length cannot be greater than maximum length.');
		}

		$this->min = $minChars;

		return $this;
	}

	public function maxLengthOf(int $maxChars): self
	{
		if ($maxChars < 0) {
			throw new InvalidArgumentException('Maximum length must be a positive integer.');
		}

		if ($maxChars < $this->min) {
			throw new InvalidArgumentException('Maximum length cannot be less than minimum length.');
		}

		if ($maxChars > PHP_INT_MAX) {
			throw new InvalidArgumentException('Maximum length cannot exceed PHP_INT_MAX.');
		}

		$this->max = $maxChars;

		return $this;
	}

	protected function cast(mixed $value): string
	{
		return $value;
	}

	protected function validateType(mixed $value): bool
	{
		return is_string($value) && preg_match(self::PATTERN, $value) === 1;
	}

	protected function getConstraints(): array
	{
		return [
			'min' => $this->validateMin(...),
			'max' => $this->validateMax(...),
		];
	}

	private function validateMin(mixed $value): bool
	{
		return mb_strlen($value) >= $this->min;
	}

	private function validateMax(mixed $value): bool
	{
		return mb_strlen($value) <= $this->max;
	}

	/**
	 * @return SerializedUri
	 */
	public function serialize(): object
	{
		return (object)[
			'type' => $this->type->value,
			'name' => $this->name->value,
			'optional' => $this->optional,
			'value' => $this->defaultValue->unwrap(),
			'fields' => [],
			'min' => $this->min,
			'max' => $this->max,
		];
	}

	/**
	 * @param SerializedUri $serialized
	 */
	public static function deserialize(object $serialized): static
	{
		if ($serialized->type !== 'uri') {
			throw new InvalidArgumentException('Invalid serialized type for Uri field.');
		}

		$instance = new self(new Property\Name($serialized->name));
		$instance->optional = $serialized->optional;

		return $instance
			->minLengthOf($serialized->min)
			->maxLengthOf($serialized->max)
			->prefill($serialized->value);
	}
}
