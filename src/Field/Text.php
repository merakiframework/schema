<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Atomic as AtomicField;
use Meraki\Schema\Property;
use InvalidArgumentException;

/**
 * @extends AtomicField<string|null>
 */
final class Text extends AtomicField
{
	/**
	 * The minimum number of characters allowed in the string. Defaults to 0.
	 * A value of 0 means that an empty string is allowed.
	 * @property non-negative-int $minLength */
	public private(set) int $minLength = 0;

	/** @property non-negative-int|null $maxLength `null` means no limit. */
	public private(set) ?int $maxLength = null;

	/** @property string|null $pattern `null` means no pattern was set. */
	public private(set) ?string $pattern = null;

	public function __construct(
		Property\Name $name,
	) {
		parent::__construct($name);
	}

	public function minLengthOf(int $characters): self
	{
		if ($characters < 0) {
			throw new InvalidArgumentException('A minimum length cannot be negative.');
		}

		if ($this->maxLength !== null && $characters > $this->maxLength) {
			throw new InvalidArgumentException('A minimum length cannot exceed the maximum.');
		}

		$this->minLength = $characters;

		return $this;
	}

	public function maxLengthOf(?int $characters): self
	{
		if ($characters === null) {
			$this->maxLength = null;

			return $this;
		}

		if ($characters < 0) {
			throw new InvalidArgumentException('A maximum length cannot be negative.');
		}

		if ($characters < $this->minLength) {
			throw new InvalidArgumentException('A maximum length cannot be less than the minimum.');
		}

		$this->maxLength = $characters;

		return $this;
	}

	/**
	 * Named for the rule it states rather than the question it looks like, as
	 * {@see Boolean::mustBeAccepted()} is. Passing null clears it.
	 */
	public function mustMatch(?string $regex): self
	{
		$this->assertValidRegex($regex);

		$this->pattern = $regex;

		return $this;
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
		return (new Constraint\Set())
			->and('minLength', fn(mixed $v): bool => mb_strlen($v) >= $this->minLength, $this->minLength)
			->and('maxLength', fn(mixed $v): ?bool => $this->maxLength === null ? null : mb_strlen($v) <= $this->maxLength, $this->maxLength)
			->and('pattern', fn(mixed $v): ?bool => $this->pattern === null ? null : preg_match($this->pattern, $v) === 1, $this->pattern);
	}

	protected function getConstraints(): array
	{
		return [];
	}
}
