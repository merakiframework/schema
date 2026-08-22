<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Field\ConstraintValidationResult;
use Meraki\Schema\Rule\AppliedOutcome;
use InvalidArgumentException;
use LogicException;

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
 * @extends AggregatedValidationResult<ConstraintValidationResult>
 */
final class ResolvedField extends AggregatedValidationResult
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
	 * @param ConstraintValidationResult ...$results empty until validation runs, which is
	 *        why a merely-resolved field reports {@see ValidationStatus::Pending}.
	 */
	public function __construct(
		public readonly Field $field,
		public readonly mixed $given,
		public readonly mixed $value,
		public readonly array $appliedOutcomes = [],
		ConstraintValidationResult ...$results,
	) {
		parent::__construct(...$results);

		$this->assertResultsAreUnique();
	}

	/**
	 * The value in whatever type the field is really about — a `BigDecimal` for money, a
	 * `LocalDate` for a date, a parsed phone number.
	 *
	 * `null` when the field was skipped: nothing was supplied and nothing was required, so
	 * there is legitimately no value. Reading it after a failure throws, because there is
	 * no sensible answer and quietly returning null would hide the difference between
	 * "absent" and "wrong".
	 *
	 * @throws LogicException when the field failed, or has not been validated yet
	 */
	public mixed $transformed {
		get => match ($this->status) {
			ValidationStatus::Passed => $this->field->transform($this->value),
			ValidationStatus::Skipped => null,
			ValidationStatus::Failed => throw new LogicException(sprintf(
				'"%s" failed validation (%s), so it has no transformed value. Check the result before reading it.',
				(string) $this->field->name,
				implode(', ', array_map(
					static fn(ConstraintValidationResult $r): string => $r->name,
					iterator_to_array($this->getFailed()),
				)) ?: 'no constraint reported',
			)),
			ValidationStatus::Pending => throw new LogicException(sprintf(
				'"%s" has not been validated, so it has no transformed value.',
				(string) $this->field->name,
			)),
		};
	}

	/**
	 * The result for one constraint, by the name it is reported under.
	 */
	public function get(string $constraintName): ?ConstraintValidationResult
	{
		if ($constraintName === '') {
			throw new InvalidArgumentException('Constraint name cannot be empty.');
		}

		foreach ($this->results as $result) {
			if ($result->name === $constraintName) {
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
	public function withResults(ConstraintValidationResult ...$results): self
	{
		return new self($this->field, $this->given, $this->value, $this->appliedOutcomes, ...$results);
	}

	private function assertResultsAreUnique(): void
	{
		$seen = [];

		foreach ($this->results as $result) {
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
