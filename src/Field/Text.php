<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Exception\InvalidConfiguration;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\ValueScope;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Field\Text\Value;
use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;

/**
 * @extends AtomicField<string|null>
 */
final readonly class Text extends AtomicField
{
	/**
	 * The fewest characters accepted. Zero allows the empty string; one does not.
	 *
	 * @var non-negative-int
	 */
	public int $minLength;

	/**
	 * The most accepted; `null` means no limit.
	 *
	 * @var non-negative-int|null
	 */
	public ?int $maxLength;

	/**
	 * A PCRE the whole string must match, delimiters and all; `null` means none is required.
	 *
	 * @var string|null
	 */
	public ?string $pattern;

	public function __construct(
		public FieldName $name,
	) {
		parent::__construct();

		$this->minLength = self::initially(0);
		$this->maxLength = self::initially(null);
		$this->pattern = self::initially(null);

		// Last: every property it reads must already be set.
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * The fewest characters this field accepts. Zero allows the empty string; one does not.
	 *
	 * @param non-negative-int $characters
	 * @throws InvalidConfiguration if negative, or above the maximum
	 */
	public function minLengthOf(int $characters): static
	{
		if ($characters < 0) {
			throw InvalidConfiguration::minimumIsNegative('length');
		}

		if ($this->maxLength !== null && $characters > $this->maxLength) {
			throw InvalidConfiguration::minimumExceedsMaximum('length');
		}

		return $this->with(['minLength' => $characters]);
	}

	/**
	 * The most this field accepts.
	 *
	 * @param non-negative-int|null $characters `null` removes the limit
	 * @throws InvalidConfiguration if negative, or below the minimum
	 */
	public function maxLengthOf(?int $characters): static
	{
		if ($characters === null) {
			return $this->with(['maxLength' => null]);
		}

		if ($characters < 0) {
			throw InvalidConfiguration::maximumIsNegative('length');
		}

		if ($characters < $this->minLength) {
			throw InvalidConfiguration::maximumIsBelowMinimum('length');
		}

		return $this->with(['maxLength' => $characters]);
	}

	/**
	 * What a rule may ask about this field: text has no order: "banana" is not before "cherry" in any sense a form means.
	 *
	 * @see \Meraki\Schema\Rule\Matcher for the four sets and why a field declares one
	 */
	public function when(): Matcher\Text
	{
		return new Matcher\Text(ValueScope::of($this->name));
	}

	/**
	 * A PCRE the whole string must match, given with its delimiters as `Text::matching()` takes it.
	 *
	 * @param string|null $regex `null` removes the pattern
	 * @throws InvalidConfiguration if the pattern is not a valid PCRE
	 */
	public function mustMatch(?string $regex): static
	{
		if ($regex === null) {
			return $this->with(['pattern' => null]);
		}

		if (@preg_match($regex, '') === false) {
			throw InvalidConfiguration::patternIsNotAValidRegex($regex);
		}

		return $this->with(['pattern' => $regex]);
	}

	protected function parse(mixed $value): Value
	{
		if ($value instanceof Value) {
			return $value;
		}

		if (!is_string($value)) {
			throw MalformedValue::of(Value::class, 'text is submitted as a string');
		}

		return new Value($value);
	}

	protected function defineConstraints(): Constraint\Set
	{
		return new Constraint\Set(
			new Constraint('minLength', $this->meetsMinimumLength(...), $this->minLength),
			new Constraint('maxLength', $this->meetsMaximumLength(...), $this->maxLength),
			new Constraint('pattern', $this->matchesPattern(...), $this->pattern),
		);
	}

	private function meetsMinimumLength(Value $parsed): bool
	{
		$value = $parsed->text;

		return mb_strlen($value) >= $this->minLength;
	}

	private function meetsMaximumLength(Value $parsed): ?bool
	{
		$value = $parsed->text;

		return $this->maxLength === null ? null : mb_strlen($value) <= $this->maxLength;
	}

	private function matchesPattern(Value $parsed): ?bool
	{
		$value = $parsed->text;

		return $this->pattern === null ? null : preg_match($this->pattern, $value) === 1;
	}
}
