<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\ValidationResult;
use Meraki\Schema\ValidationStatus;

/**
 * What one constraint said about one value.
 *
 * It carries three things, and the second two exist so that nothing downstream has to go
 * looking:
 *
 * - `$part` says which part of a structured value failed, so a renderer can attach the
 *   error to the right input. It used to be encoded in the name — `billing.postal_code.format`
 *   — and read back out with `strrpos()`, which made the field's own name part of every
 *   constraint it emitted and left no way to express "this is about the whole value".
 * - `$bound` is the limit that applied. A message provider used to reach for it with
 *   `$field->{$constraint->name}`, a dynamic property read that static analysis cannot type
 *   and that renders "Array" for a bound held as a map. Carrying it here also makes a
 *   computed bound reportable at all — a per-country postal format has no property to point
 *   at.
 */
final class ConstraintValidationResult implements ValidationResult
{
	/**
	 * @param string $name what was checked, e.g. `minLength`
	 * @param string|null $part the part of a structured value this concerns, e.g. `postalCode`;
	 *        null when it concerns the whole value
	 * @param string|int|float|bool|list<string>|null $bound the limit that applied, ready to
	 *        put in a message; null when there is nothing to interpolate
	 */
	public function __construct(
		public readonly ValidationStatus $status,
		public readonly string $name,
		public readonly ?string $part = null,
		public readonly string|int|float|bool|array|null $bound = null,
	) {
	}

	/**
	 * @param string|int|float|bool|list<string>|null $bound
	 */
	public static function pass(
		string $name,
		?string $part = null,
		string|int|float|bool|array|null $bound = null,
	): self {
		return new self(ValidationStatus::Passed, $name, $part, $bound);
	}

	/**
	 * @param string|int|float|bool|list<string>|null $bound
	 */
	public static function fail(
		string $name,
		?string $part = null,
		string|int|float|bool|array|null $bound = null,
	): self {
		return new self(ValidationStatus::Failed, $name, $part, $bound);
	}

	/**
	 * @param string|int|float|bool|list<string>|null $bound
	 */
	public static function skip(
		string $name,
		?string $part = null,
		string|int|float|bool|array|null $bound = null,
	): self {
		return new self(ValidationStatus::Skipped, $name, $part, $bound);
	}

	public function passed(): bool
	{
		return $this->status === ValidationStatus::Passed;
	}

	public function failed(): bool
	{
		return $this->status === ValidationStatus::Failed;
	}

	public function skipped(): bool
	{
		return $this->status === ValidationStatus::Skipped;
	}
}
