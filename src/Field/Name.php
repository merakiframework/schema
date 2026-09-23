<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Exception\InvalidConfiguration;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\ValueScope;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Field\Name\Value;
use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;

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
 * @extends AtomicField<string|null>
 * @see https://www.w3.org/International/questions/qa-personal-names
 * @see https://shinesolutions.com/2018/01/08/falsehoods-programmers-believe-about-names-with-examples/
 */
final readonly class Name extends AtomicField
{
	/** @var non-negative-int $minLength */
	public int $minLength;

	/**
	 * What a rule may ask about this field: as with any text, there is no order worth asking about.
	 *
	 * @see \Meraki\Schema\Rule\Matcher for the four sets and why a field declares one
	 */
	public function when(): Matcher\Text
	{
		return new Matcher\Text(ValueScope::of($this->name));
	}

	/** @var non-negative-int|null $maxLength */
	public ?int $maxLength;

	public function __construct(
		public FieldName $name,
	) {
		parent::__construct();

		$this->minLength = 1;
		$this->maxLength = 255;
		$this->constraints = $this->defineConstraints();
	}

	public function minLengthOf(int $minChars): self
	{
		if ($minChars < 1) {
			throw InvalidConfiguration::minimumLengthWouldRejectNothing();
		}

		if ($this->maxLength !== null && $minChars > $this->maxLength) {
			throw InvalidConfiguration::minimumExceedsMaximum('length');
		}

		return $this->with(['minLength' => $minChars]);
	}

	public function maxLengthOf(?int $maxChars): self
	{
		if ($maxChars === null) {
			return $this->with(['maxLength' => null]);
		}

		if ($maxChars < 1) {
			throw InvalidConfiguration::maximumLengthWouldAcceptNothing();
		}

		if ($maxChars < $this->minLength) {
			throw InvalidConfiguration::maximumIsBelowMinimum('length');
		}

		return $this->with(['maxLength' => $maxChars]);
	}

	protected function parse(mixed $value): Value
	{
		if ($value instanceof Value) {
			return $value;
		}

		if (!is_string($value)) {
			throw MalformedValue::of(Value::class, 'a name is submitted as a string');
		}

		return new Value($value);
	}

	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			new Constraint('minLength', $this->meetsMinLength(...), $this->minLength),
			new Constraint('maxLength', $this->meetsMaxLength(...), $this->maxLength),
		);
	}

	private function meetsMinLength(Value $parsed): bool
	{
		$value = $parsed->name;

		return mb_strlen($value) >= $this->minLength;
	}

	private function meetsMaxLength(Value $parsed): ?bool
	{
		$value = $parsed->name;

		return $this->maxLength === null ? null : mb_strlen($value) <= $this->maxLength;
	}
}
