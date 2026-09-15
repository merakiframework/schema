<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Field\ConstraintValidationResult;
use Meraki\Schema\Rule\AppliedOutcome;
use Brick\DateTime\Instant;
use Meraki\Schema\ValueSource;
use InvalidArgumentException;

/**
 * One field, resolved against one request.
 *
 * A {@see Field} describes what a field *is* and never changes once built. Everything that
 * belongs to a single request — the submitted value, the value actually validated, which
 * rules altered the field, and what the constraints said — lives here instead. That is
 * what makes a schema safe to share: nothing per-request is written back onto it.
 *
 * It *is* the field's validation result rather than holding one, so the predicates from
 * {@see AggregatedValidationResult} — `anyFailed()`, `getFailed()`, `status` — apply
 * directly.
 *
 * Not final, so a field with something extra to report can subclass it — see
 * {@see Field\Password\Result}, which adds the measured entropy of the submitted secret. The seam
 * is deliberately narrow: a subclass adds *readings*, never verdicts, which still come from
 * constraints. A field whose result has a different *shape* implements {@see FieldResult} instead,
 * the way {@see Field\Collection\Result} does.
 *
 * @extends AggregatedValidationResult<ValidationResult>
 */
class ResolvedField extends AggregatedValidationResult implements FieldResult
{
	/**
	 * @param Field $field the *effective* definition: the authored one, plus any change a
	 *        matching rule made. The authored version stays on the schema.
	 * @param mixed $given exactly what was submitted, unchanged. Re-rendering a rejected
	 *        form must echo this back rather than anything coerced, or the user is shown
	 *        something they did not type.
	 * @param mixed $value what was actually validated: `$given`, or the field's default
	 *        when nothing was submitted.
	 * @param list<AppliedOutcome> $appliedOutcomes which rules changed this field, and how.
	 * @param ValueSource $source where `$value` came from — submitted, prefilled for this one
	 *        user, the schema's own default, or nowhere.
	 * @param Instant|null $evaluatedAt the instant any time-relative constraint was judged
	 *        against, or null when this field has no clock because nothing about it depends on
	 *        the time. Recording it is what makes a verdict reproducible: re-asking the same
	 *        question of the same input at the same instant gives the same answer.
	 * @param ValidationResult ...$results empty until validation runs, which is why a merely
	 *        resolved field reports {@see ValidationStatus::Pending}.
	 *
	 *        Ordinarily a {@see Field\ShapeValidationResult} and one
	 *        {@see ConstraintValidationResult} per constraint. Typed wider than that so a subclass
	 *        can report its own kind alongside them — {@see Field\Collection\Result} adds one per
	 *        row — and have those count towards `anyFailed()` and `$status`, which is what makes a
	 *        collection whose third row failed report as a failed field.
	 *
	 *        Everything that reads a specific kind filters for it, so an extra kind passes through
	 *        {@see self::$shape}, {@see self::$constraintNames} and {@see self::forConstraint()}
	 *        without being mistaken for a constraint.
	 */
	public function __construct(
		public readonly Field $field,
		public readonly mixed $given,
		public readonly mixed $value,
		public readonly array $appliedOutcomes = [],
		public readonly ValueSource $source = ValueSource::Submitted,
		public readonly ?Instant $evaluatedAt = null,
		ValidationResult ...$results,
	) {
		parent::__construct(...$results);

		$this->assertResultsAreUnique();
	}

	/**
	 * Whether the value could be read as this field's kind of thing at all.
	 *
	 * Separate from the constraints because it is not one: it is the gate that decides whether they
	 * run. `Failed` means either that something arrived and could not be read, or that nothing
	 * arrived and the field required it; `Skipped` means nothing arrived and that was acceptable.
	 *
	 * `Pending` when validation has not run, which is also what an empty result reports.
	 *
	 * @see Field\ShapeValidationResult for why it still lives among the results
	 */
	public Field\ShapeValidationResult $shape {
		get {
			foreach ($this->results as $result) {
				if ($result instanceof Field\ShapeValidationResult) {
					return $result;
				}
			}

			return Field\ShapeValidationResult::pending();
		}
	}

	/**
	 * Every constraint name this reported under, in order.
	 *
	 * Useful for asserting what a field emits — and for checking that a name carries no trace of
	 * the field it came from, which is the whole point of a structured field owning its value
	 * rather than being a bag of sub-fields.
	 *
	 * The shape is not among them. It never was a constraint, and listing it here is what let
	 * `type` be mistaken for one.
	 *
	 * @return list<string>
	 */
	public array $constraintNames {
		get => array_values(array_map(
			static fn(ConstraintValidationResult $result): string => $result->name,
			array_filter(
				iterator_to_array($this->results),
				static fn(object $result): bool => $result instanceof ConstraintValidationResult,
			),
		));
	}

	/**
	 * The result for one constraint, by the name it is reported under.
	 */
	public function forConstraint(string $constraintName): ?ConstraintValidationResult
	{
		if ($constraintName === '') {
			throw new InvalidArgumentException('Constraint name cannot be empty.');
		}

		foreach ($this->results as $result) {
			if ($result instanceof ConstraintValidationResult && $result->name === $constraintName) {
				return $result;
			}
		}

		return null;
	}

	/**
	 * Whether a rule altered this field on this request, rather than the author writing it
	 * that way. A renderer needs to tell those apart: a field made optional by a rule that
	 * did not match should not be drawn as though the author made it optional.
	 */
	public function wasAlteredByRule(): bool
	{
		return $this->appliedOutcomes !== [];
	}

	/**
	 * The same field with constraint results attached. Resolution and validation are two
	 * steps, because a form is rendered before it is submitted.
	 */
	public function withResults(ValidationResult ...$results): self
	{
		return new self($this->field, $this->given, $this->value, $this->appliedOutcomes, $this->source, $this->evaluatedAt, ...$results);
	}

	private function assertResultsAreUnique(): void
	{
		$seen = [];

		foreach ($this->results as $result) {
			if (!$result instanceof ConstraintValidationResult) {
				continue;
			}

			if (isset($seen[$result->name])) {
				throw new InvalidArgumentException(sprintf(
					'Duplicate constraint name "%s" on field "%s".',
					$result->name,
					(string) $this->field->name,
				));
			}

			$seen[$result->name] = true;
		}
	}
}
