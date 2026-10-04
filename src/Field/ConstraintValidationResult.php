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
 * - `$code` says what was checked, as a case of the field's own {@see Check} enum, so a consumer
 *   compares it with `===` rather than matching a string. `$name` is the same thing as the wire
 *   carries it — the key a language pack writes under.
 * - `$part` says which part of a structured value failed, so a renderer can attach the
 *   error to the right input. It used to be encoded in the name — `billing.postal_code.format`
 *   — and read back out with `strrpos()`, which made the field's own name part of every
 *   constraint it emitted and left no way to express "this is about the whole value". It is the
 *   code's to say, so it is read from there rather than carried separately.
 * - `$bound` is the limit that applied. A message provider used to reach for it with
 *   `$field->{$constraint->name}`, a dynamic property read that static analysis cannot type
 *   and that renders "Array" for a bound held as a map. Carrying it here also makes a
 *   computed bound reportable at all — a per-country postal format has no property to point
 *   at.
 */
final class ConstraintValidationResult implements ValidationResult
{
	/**
	 * @param Check $code what was checked, e.g. `Text\Check::MinLength`
	 * @param string|int|float|bool|list<string>|null $bound the limit that applied, ready to
	 *        put in a message; null when there is nothing to interpolate
	 */
	public function __construct(
		public readonly ValidationStatus $status,
		public readonly Check $code,
		public readonly string|int|float|bool|array|null $bound = null,
	) {
	}

	/** The code as the wire carries it — `minLength`, the key a language pack writes under. */
	public string $name {
		get => (string) $this->code->value;
	}

	/** The part of a structured value this concerns, or null when it concerns the whole value. */
	public ?Part $part {
		get => $this->code->part();
	}

	/**
	 * @param string|int|float|bool|list<string>|null $bound
	 */
	public static function pass(Check $code, string|int|float|bool|array|null $bound = null): self
	{
		return new self(ValidationStatus::Passed, $code, $bound);
	}

	/**
	 * @param string|int|float|bool|list<string>|null $bound
	 */
	public static function fail(Check $code, string|int|float|bool|array|null $bound = null): self
	{
		return new self(ValidationStatus::Failed, $code, $bound);
	}

	/**
	 * @param string|int|float|bool|list<string>|null $bound
	 */
	public static function skip(Check $code, string|int|float|bool|array|null $bound = null): self
	{
		return new self(ValidationStatus::Skipped, $code, $bound);
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
