<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Password;

use InvalidArgumentException;

/**
 * Represents an integer range with optional minimum and maximum bounds.
 *
 * A `Range` is immutable. You can use modifier methods like `restrictMinTo()`
 * or `unrestrictMax()` to produce a new range instance.
 */
final class Range
{
	/**
	 * Constant representing an unrestricted value for min or max.
	 */
	public const UNRESTRICTED = null;

	/**
	 * @param int|null $min Minimum allowed value (must be non-negative or null).
	 * @param int|null $max Maximum allowed value (must be non-negative or null).
	 * @throws InvalidArgumentException if min or max are negative, or if min > max.
	 */
	public function __construct(
		public readonly ?int $min = null,
		public readonly ?int $max = null,
	) {
		if ($min !== self::UNRESTRICTED && $min < 0) {
			throw new InvalidArgumentException('Minimum must be non-negative or null.');
		}

		if ($max !== self::UNRESTRICTED && $max < 0) {
			throw new InvalidArgumentException('Maximum must be non-negative or null.');
		}

		if ($min !== self::UNRESTRICTED && $max !== self::UNRESTRICTED && $min > $max) {
			throw new InvalidArgumentException('Minimum cannot be greater than maximum.');
		}
	}

	/**
	 * Create an unrestricted range.
	 */
	public static function unrestricted(): self
	{
		return new self(self::UNRESTRICTED, self::UNRESTRICTED);
	}

	/**
	 * Create a fully restricted range between the given min and max.
	 */
	public static function restricted(int $min, int $max): self
	{
		return new self($min, $max);
	}

	/**
	 * Creates a range from a tuple of two elements [min, max].
	 * An empty array returns an unrestricted range.
	 *
	 * @param array{0:int|null, 1:int|null}|array{} $tuple
	 * @throws InvalidArgumentException if tuple does not contain exactly two elements.
	 */
	public static function fromTuple(array $tuple): self
	{
		if ($tuple === []) {
			return new self();
		}

		if (count($tuple) !== 2) {
			throw new InvalidArgumentException('Tuple must contain exactly two elements or be empty.');
		}

		return new self($tuple[0], $tuple[1]);
	}

	/**
	 * Convert the range to a tuple [min, max], or [] if both are null.
	 *
	 * @return array{0:int|null, 1:int|null}|array{}
	 */
	public function toTuple(): array
	{
		if ($this->min === null && $this->max === null) {
			return [];
		}

		return [$this->min, $this->max];
	}

	/**
	 * Checks if a value is within the range.
	 */
	public function contains(int $value): bool
	{
		if ($this->min !== self::UNRESTRICTED && $value < $this->min) {
			return false;
		}

		if ($this->max !== self::UNRESTRICTED && $value > $this->max) {
			return false;
		}

		return true;
	}

	/**
	 * Returns a new range with a restricted minimum.
	 */
	public function restrictMinTo(int $min): self
	{
		return new self($min, $this->max);
	}

	/**
	 * Returns a new range with a restricted maximum.
	 */
	public function restrictMaxTo(int $max): self
	{
		return new self($this->min, $max);
	}

	/**
	 * Returns a new range with both min and max restricted.
	 */
	public function restrictTo(int $min, int $max): self
	{
		return new self($min, $max);
	}

	/**
	 * Returns true if both min and max are unrestricted.
	 */
	public function isUnrestricted(): bool
	{
		return $this->min === self::UNRESTRICTED && $this->max === self::UNRESTRICTED;
	}

	/**
	 * Returns true if either min or max is set.
	 */
	public function isRestricted(): bool
	{
		return $this->min !== self::UNRESTRICTED || $this->max !== self::UNRESTRICTED;
	}

	/**
	 * Returns a new range with no minimum restriction.
	 */
	public function unrestrictMin(): self
	{
		return new self(self::UNRESTRICTED, $this->max);
	}

	/**
	 * Returns a new range with no maximum restriction.
	 */
	public function unrestrictMax(): self
	{
		return new self($this->min, self::UNRESTRICTED);
	}

	public function equals(self $other): bool
	{
		return ($this->min === $other->min) && ($this->max === $other->max);
	}
}
