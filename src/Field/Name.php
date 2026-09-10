<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Property;

/**
 * A "name" field is used to represent a person's full name.
 *
 * It does not make any assumptions about the structure of a name.
 * There are, however, some sane restrictions. A name can:
 *  - not be empty
 *  - contain unicode letters, spaces, apostrophes, periods, commas, and dashes
 *  - consist of one or more "words" separated by spaces
 *  - each "word" must be at least one character long
 *  - should use Roman Numerals to represent numbers (e.g. John Doe IV)
 *
 * @extends Field<string|null>
 * @see https://www.w3.org/International/questions/qa-personal-names
 * @see https://shinesolutions.com/2018/01/08/falsehoods-programmers-believe-about-names-with-examples/
 */
final class Name extends Field
{
	private const PATTERN = "/^(?![\ \.\,\'\-]+$)[\p{L}\.\,\'\ \-]+$/u";

	public private(set) int $minLength = 1;

	public private(set) ?int $maxLength = 255;

	public function __construct(
		public readonly Property\Name $name,
	) {
	}

	public function minLengthOf(int $minChars): self
	{
		$this->minLength = $minChars;

		return $this;
	}

	public function maxLengthOf(?int $maxChars): self
	{
		if ($maxChars === null) {
			$this->maxLength = null;

			return $this;
		}

		$this->maxLength = $maxChars;

		return $this;
	}

	protected function cast(mixed $value): string
	{
		return (string)$value;
	}

	public function validateValue(mixed $value): bool
	{
		return is_string($value) && preg_match(self::PATTERN, $value) === 1;
	}

	public function constraints(): Constraint\Set
	{
		return (new Constraint\Set())
			->and('minLength', fn(mixed $v): bool => mb_strlen($v) >= $this->minLength, $this->minLength)
			->and('maxLength', fn(mixed $v): ?bool => $this->maxLength === null ? null : mb_strlen($v) <= $this->maxLength, $this->maxLength);
	}

	protected function getConstraints(): array
	{
		return [];
	}
}
