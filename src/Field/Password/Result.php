<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Password;

use Meraki\Schema\Field\ConstraintValidationResult;
use Meraki\Schema\Field\ShapeValidationResult;
use Meraki\Schema\Field\Password;
use Meraki\Schema\ResolvedField;
use Meraki\Schema\ValidationResult;
use Brick\DateTime\Instant;
use Meraki\Schema\ValueSource;
use SensitiveParameter;

/**
 * A password resolved against one request, plus the one measurement only this field can make.
 *
 * `minStrength` answers "is it strong enough", which is a verdict. {@see self::$entropy} answers
 * "how strong is it", which is a number — and a form needs the number to draw a strength meter
 * whether or not a threshold was set, or when the secret failed a *different* constraint. Reading
 * it off the constraint result cannot work: a skipped constraint carries no measurement.
 *
 * Why a subclass rather than a property on {@see ResolvedField}: entropy is meaningless for a date
 * or a postcode, and a base class carrying every field's diagnostics would grow one property per
 * field type. {@see \Meraki\Schema\Field\Collection\Result} took the same route for the same kind
 * of reason.
 */
final class Result extends ResolvedField
{
	/**
	 * Computed at most once, and only if something asks. zxcvbn's dictionaries cost ~18ms and
	 * ~10MB to load, so a field with no strength requirement must not pay for them just by being
	 * validated.
	 */
	private ?int $measured = null;

	private bool $hasMeasured = false;

	public function __construct(
		private readonly Password $password,
		mixed $given,
		mixed $value,
		array $appliedOutcomes = [],
		ValueSource $source = ValueSource::Submitted,
		?Instant $evaluatedAt = null,
		ValidationResult ...$results,
	) {
		parent::__construct($password, $given, $value, $appliedOutcomes, $source, $evaluatedAt, ...$results);
	}

	/**
	 * How much entropy the submitted secret carries, in bits, or `null` when there was nothing to
	 * measure — no input, or something that was not a string.
	 *
	 * Available whatever the verdict, deliberately. A secret that failed `maxLength` still has a
	 * strength worth showing, and a form redrawing the field wants the meter either way. This is
	 * the estimate zxcvbn gives, with the caveats on {@see Password::entropyOf()}: the dictionaries
	 * are English-centric, and a genuinely random secret is under-estimated.
	 */
	public ?int $entropy {
		get {
			if (!$this->hasMeasured) {
				$this->measured = $this->measure($this->value);
				$this->hasMeasured = true;
			}

			return $this->measured;
		}
	}

	/**
	 * Only a value the field could actually read is measured.
	 *
	 * `$value` holds the raw submission when parsing failed — which for this field means something
	 * that was not a string at all — so the type test is what tells "a secret" from "whatever
	 * arrived". An empty secret has no entropy worth reporting and is the shape check's business.
	 */
	private function measure(#[SensitiveParameter] mixed $value): ?int
	{
		return $value instanceof Value && $value->secret !== ''
			? $this->password->entropyOf($value->secret)
			: null;
	}

	/**
	 * Kept as a {@see self} so attaching verdicts does not drop back to a plain
	 * {@see ResolvedField} and lose the measurement.
	 */
	public function withResults(ValidationResult ...$results): self
	{
		return new self($this->password, $this->given, $this->value, $this->appliedOutcomes, $this->source, $this->evaluatedAt, ...$results);
	}
}
