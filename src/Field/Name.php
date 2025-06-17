<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Property;

/**
 * @extends Serialized<string|null>
 * @property-read int $min
 * @property-read int $max
 * @internal
 */
interface SerializedName extends Serialized
{
}

/**
 * A "name" field is used to represent a person's full name.
 *
 * It does not make any assumptions about the structure of a name.
 * There are, however, some sane restrictions. A name can:

 *  - not be empty
 * 	- contain unicode letters, spaces, apostrophes, periods, commas, and dashes
 * 	- consist of one or more "words" separated by spaces
 * 	- each "word" must be at least one character long
 *  - should use Roman Numerals to represent numbers (e.g. John Doe IV)
 *
 * @extends AtomicField<string|null, SerializedName>
 * @see https://www.w3.org/International/questions/qa-personal-names
 * @see https://shinesolutions.com/2018/01/08/falsehoods-programmers-believe-about-names-with-examples/
 */
final class Name extends AtomicField
{
	private const PATTERN = "/^(?![\ \.\,\'\-]+$)[\p{L}\.\,\'\ \-]+$/u";

	public int $min = 1;

	public int $max = 255;

	public function __construct(
		Property\Name $name,
	) {
		parent::__construct(new Property\Type('name', $this->validateType(...)), $name);
	}

	public function minLengthOf(int $minChars): self
	{
		$this->min = $minChars;

		return $this;
	}

	public function maxLengthOf(int $maxChars): self
	{
		$this->max = $maxChars;

		return $this;
	}

	protected function cast(mixed $value): string
	{
		return (string)$value;
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

	private function validateMin(string $value): bool
	{
		return mb_strlen($value) >= $this->min;
	}

	private function validateMax(string $value): bool
	{
		return mb_strlen($value) <= $this->max;
	}

	public function serialize(): SerializedName
	{
		return new class(
			type: $this->type->value,
			name: $this->name->value,
			optional: $this->optional,
			value: $this->defaultValue->unwrap(),
			min: $this->min,
			max: $this->max,
			fields: [],
		) implements SerializedName {
			public function __construct(
				public readonly string $type,
				public readonly string $name,
				public readonly bool $optional,
				public readonly mixed $value,
				public readonly int $min,
				public readonly int $max,
				/** @var array<Serialized> */
				public readonly array $fields,
			) {
			}
		};
	}

	/**
	 * @param SerializedName $serialized
	 */
	public static function deserialize(Serialized $serialized): static
	{
		$field = new self(new Property\Name($serialized->name));
		$field->optional = $serialized->optional;

		return $field->minLengthOf($serialized->min)
			->maxLengthOf($serialized->max)
			->prefill($serialized->value);
	}
}
