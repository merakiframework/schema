<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\ValueScope;
use Meraki\Schema\Rule\Matcher;
use Meraki\Schema\Field\Text\Value;
use Meraki\Schema\AtomicField;
use Meraki\Schema\FieldName;
use InvalidArgumentException;

/**
 * @extends AtomicField<string|null>
 */
final readonly class Text extends AtomicField
{
	/**
	 * The minimum number of characters allowed in the string. Defaults to 0.
	 * A value of 0 means that an empty string is allowed.
	 * A value of 1 means that an empty string is not allowed.
	 * @var non-negative-int $minLength
	 */
	public int $minLength;

	/**
	 * The maximum number of characters allowed in the string. Defaults to `null`, which means no limit.
	 * @var non-negative-int|null $maxLength `null` means no limit.
	 */
	public ?int $maxLength;

	/**
	 * A regular expression pattern that the string must match. Defaults to `null`, which means no pattern is required.
	 * This pattern must be a valid PCRE2 regular expression.
	 * @var string|null $pattern `null` means no pattern was set.
	 */
	public ?string $pattern;

	public function __construct(
		public FieldName $name,
	) {
		parent::__construct();

		$this->minLength = 0;
		$this->maxLength = null;
		$this->pattern = null;

		// Last: every property it reads must already be set.
		$this->constraints = $this->defineConstraints();
	}

	/**
	 * Sets the minimum length of the string. A value of 0 means that an empty string is allowed.
	 * A value of 1 means that an empty string is not allowed.
	 * @param non-negative-int $characters The minimum number of characters allowed in the string.
	 * @throws InvalidArgumentException If the minimum length is negative or exceeds the maximum length.
	 * @throws InvalidArgumentException If the maximum length is set and the minimum length exceeds it.
	 */
	public function minLengthOf(int $characters): static
	{
		if ($characters < 0) {
			throw new InvalidArgumentException('A minimum length cannot be negative.');
		}

		if ($this->maxLength !== null && $characters > $this->maxLength) {
			throw new InvalidArgumentException('A minimum length cannot exceed the maximum.');
		}

		return $this->with(['minLength' => $characters]);
	}

	/**
	 * Sets the maximum length of the string. A value of `null` means no limit.
	 * @param non-negative-int|null $characters The maximum number of characters allowed in the string, or `null` for no limit.
	 * @throws InvalidArgumentException If the maximum length is negative or less than the minimum length.
	 * @throws InvalidArgumentException If the minimum length is set and the maximum length is less than it.
	 */
	public function maxLengthOf(?int $characters): static
	{
		if ($characters === null) {
			return $this->with(['maxLength' => null]);
		}

		if ($characters < 0) {
			throw new InvalidArgumentException('A maximum length cannot be negative.');
		}

		if ($characters < $this->minLength) {
			throw new InvalidArgumentException('A maximum length cannot be less than the minimum.');
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
	 * Sets a regular expression pattern that the string must match. A value of `null` means no pattern is required.
	 * @param string|null $regex The regular expression pattern that the string must match, or `null` for no pattern.
	 * @throws InvalidArgumentException If the provided regular expression is invalid.
	 */
	public function mustMatch(?string $regex): static
	{
		if ($regex === null) {
			return $this->with(['pattern' => null]);
		}

		if (@preg_match($regex, '') === false) {
			throw new InvalidArgumentException('Invalid regular expression provided.');
		}

		return $this->with(['pattern' => $regex]);
	}

	protected function parse(mixed $value): ?Value
	{
		return is_string($value) ? new Value($value) : null;
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
