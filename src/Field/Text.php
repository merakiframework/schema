<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Property;
use InvalidArgumentException;

/**
 * @extends Field<string|null>
 */
final class Text extends Field
{
	/**
	 * The minimum number of characters allowed in the string. Defaults to 0.
	 * A value of 0 means that an empty string is allowed.
	 * A value of 1 means that an empty string is not allowed.
	 * @property non-negative-int $minLength
	 */
	public private(set) int $minLength = 0;

	/**
	 * The maximum number of characters allowed in the string. Defaults to `null`, which means no limit.
	 * @property non-negative-int|null $maxLength `null` means no limit.
	 */
	public private(set) ?int $maxLength = null;

	/**
	 * A regular expression pattern that the string must match. Defaults to `null`, which means no pattern is required.
	 * This pattern must be a valid PCRE2 regular expression.
	 * @property string|null $pattern `null` means no pattern was set.
	 */
	public private(set) ?string $pattern = null;

	public function __construct(
		public readonly Property\Name $name,
	) {
	}

	/**
	 * Sets the minimum length of the string. A value of 0 means that an empty string is allowed.
	 * A value of 1 means that an empty string is not allowed.
	 * @param non-negative-int $characters The minimum number of characters allowed in the string.
	 * @throws InvalidArgumentException If the minimum length is negative or exceeds the maximum length.
	 * @throws InvalidArgumentException If the maximum length is set and the minimum length exceeds it.
	 */
	public function minLengthOf(int $characters): self
	{
		if ($characters < 0) {
			throw new InvalidArgumentException('A minimum length cannot be negative.');
		}

		if ($this->maxLength !== null && $characters > $this->maxLength) {
			throw new InvalidArgumentException('A minimum length cannot exceed the maximum.');
		}

		return clone($this, ['minLength' => $characters]);
	}

	/**
	 * Sets the maximum length of the string. A value of `null` means no limit.
	 * @param non-negative-int|null $characters The maximum number of characters allowed in the string, or `null` for no limit.
	 * @throws InvalidArgumentException If the maximum length is negative or less than the minimum length.
	 * @throws InvalidArgumentException If the minimum length is set and the maximum length is less than it.
	 */
	public function maxLengthOf(?int $characters): self
	{
		if ($characters === null) {
			return clone($this, ['maxLength' => null]);
		}

		if ($characters < 0) {
			throw new InvalidArgumentException('A maximum length cannot be negative.');
		}

		if ($characters < $this->minLength) {
			throw new InvalidArgumentException('A maximum length cannot be less than the minimum.');
		}

		return clone($this, ['maxLength' => $characters]);
	}

	/**
	 * Sets a regular expression pattern that the string must match. A value of `null` means no pattern is required.
	 * @param string|null $regex The regular expression pattern that the string must match, or `null` for no pattern.
	 * @throws InvalidArgumentException If the provided regular expression is invalid.
	 */
	public function mustMatch(?string $regex): self
	{
		$this->assertValidRegex($regex);

		return clone($this, ['pattern' => $regex]);
	}

	private function assertValidRegex(?string $regex): void
	{
		if ($regex === null) {
			return;
		}

		if (@preg_match($regex, '') === false) {
			throw new InvalidArgumentException('Invalid regular expression provided.');
		}
	}

	/**
	 * {@inheritDoc}
	 */
	protected function cast(string $value): mixed
	{
		return $value;
	}

	public function validateValue(mixed $value): bool
	{
		return is_string($value);
	}

	public function constraints(): Constraint\Set
	{
		return new Constraint\Set(
			new Constraint('minLength', $this->meetsMinimumLength(...), $this->minLength),
			new Constraint('maxLength', $this->meetsMaximumLength(...), $this->maxLength),
			new Constraint('pattern', $this->matchesPattern(...), $this->pattern),
		);
	}

	private function meetsMinimumLength(string $value): bool
	{
		return mb_strlen($value) >= $this->minLength;
	}

	private function meetsMaximumLength(string $value): ?bool
	{
		return $this->maxLength === null ? null : mb_strlen($value) <= $this->maxLength;
	}

	private function matchesPattern(string $value): ?bool
	{
		return $this->pattern === null ? null : preg_match($this->pattern, $value) === 1;
	}
}
