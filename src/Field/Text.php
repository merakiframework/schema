<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Field;
use Meraki\Schema\Property;
use InvalidArgumentException;

/**
 * @phpstan-import-type SerializedField from Field
 * @phpstan-type SerializedText = SerializedField&object{
 * 	type: 'text',
 * 	min: int,
 * 	max: int,
 * 	pattern: string|null
 * }
 * @extends AtomicField<string|null, SerializedText>
 */
final class Text extends AtomicField
{
	public const SKIP_MATCHING = null;

	public int $min = 0;

	public int $max = PHP_INT_MAX;

	public ?string $pattern = self::SKIP_MATCHING;

	public function __construct(
		Property\Name $name,
	) {
		parent::__construct(new Property\Type('text', $this->validateType(...)), $name);
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

	public function matches(?string $regex): self
	{
		$this->assertValidRegex($regex);

		$this->pattern = $regex;

		return $this;
	}

	private function assertValidRegex(?string $regex): void
	{
		if ($regex === null) {
			return; // Skip validation if no pattern is set
		}

		if (@preg_match($regex, '') === false) {
			throw new InvalidArgumentException('Invalid regular expression provided.');
		}
	}

	protected function cast(string $value): mixed
	{
		return $value;
	}

	protected function validateType(mixed $value): bool
	{
		return is_string($value);
	}

	protected function getConstraints(): array
	{
		return [
			'min' => $this->validateMin(...),
			'max' => $this->validateMax(...),
			'pattern' => $this->validatePattern(...),
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

	private function validatePattern(mixed $value): ?bool
	{
		if ($this->pattern === self::SKIP_MATCHING) {
			return null; // Skip validation if no pattern is set
		}

		return preg_match($this->pattern, $value) === 1;
	}

	/**
	 * @return SerializedText
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
			'pattern' => $this->pattern,
		];
	}

	/**
	 * @param SerializedText $data
	 */
	public static function deserialize(object $data): static
	{
		if ($data->type !== 'text') {
			throw new InvalidArgumentException('Invalid type for Text field.');
		}

		$field = new static(new Property\Name($data->name));
		$field->optional = $data->optional;

		return $field->minLengthOf($data->min)
			->maxLengthOf($data->max)
			->matches($data->pattern)
			->prefill($data->value);
	}
}
