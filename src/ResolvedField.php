<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Exception\InvalidConstraint;
use Meraki\Schema\Field\ConstraintValidationResult;
use Meraki\Schema\Rule\AppliedOutcome;
use Brick\DateTime\Instant;
use Meraki\Schema\ValueSource;

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
	 * @param Field\ParsedValue|null $value what the field made of the input — the parsed form of
	 *        whichever source won, or `null` when nothing valid could be read. Never the raw
	 *        input: that is `$given`, and having both mean it made the type unusable.
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

		$this->messages = Message\Set::for($this, null);
	}

	/**
	 * What to tell somebody about this field, in the language the request asked for.
	 *
	 * Empty unless a {@see Message\Provider} was registered on the schema *and* the request named
	 * a language it has, which is why a field validated on its own always has nothing here: there
	 * is no schema to have carried a provider. That is the trade, and it is deliberate — a field
	 * is a definition, and a definition that knew about languages would be a definition that could
	 * not be serialised the same way twice.
	 *
	 * Always a set, never null, so reading it needs no guard. {@see Message\Set} explains which of
	 * the two shapes it takes and why the field rather than the failures decides.
	 */
	public protected(set) Message\Set $messages;

	/**
	 * The same field with its messages rendered in one language.
	 *
	 * Called by {@see Facade::validate()} once per field, after the verdicts are in, because
	 * nothing about a language may change a verdict. A result that never goes through here keeps
	 * the empty set it was built with.
	 *
	 * Rendering eagerly rather than holding the translator keeps the result a plain value: what it
	 * says is fixed at the moment it was judged, and cannot come out differently on a second read
	 * because somebody changed a pack in between.
	 */
	public function withMessagesFrom(?Message\Translator $translator): static
	{
		return clone($this, ['messages' => Message\Set::for($this, $translator)]);
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
	 * Just this field's constraint verdicts, as an aggregate of their own.
	 *
	 * The richer half of the pair. `$shape` answers one question and this answers the rest, so
	 * everything {@see AggregatedValidationResult} offers — `getFailed()`, `allPassed()`,
	 * `filter()`, iteration — works on the constraints alone rather than on constraints mixed with
	 * the shape and, for a collection, with a verdict per row:
	 *
	 *     $field->constraints->getFailed()->getFirst();   // the first constraint that failed
	 *     $field->constraints->allPassed();               // every check the field makes
	 *
	 * The common readings have shorthand on this object — {@see self::getFailedConstraints()},
	 * {@see self::forConstraint()} — so a caller reaches for this only when they want more.
	 */
	public Field\ConstraintResults $constraints {
		get => new Field\ConstraintResults(...array_values(array_filter(
			iterator_to_array($this->results),
			static fn(object $result): bool => $result instanceof ConstraintValidationResult,
		)));
	}

	/**
	 * Whether the value could not be read as this field's kind of thing.
	 *
	 * Shorthand for `$field->shape->wasUnreadable()`. The nesting is still there when you want the
	 * shape's own API; this is here because asking "was that readable" is the common case and
	 * should not cost two hops.
	 */
	public function wasUnreadable(): bool
	{
		return $this->shape->wasUnreadable();
	}

	/**
	 * Whether nothing was submitted for a field that required something.
	 *
	 * Shorthand for `$field->shape->wasMissing()`. Distinct from {@see self::wasUnreadable()}
	 * because "this is required" and "this is not a valid duration" are different sentences.
	 */
	public function wasMissing(): bool
	{
		return $this->shape->wasMissing();
	}

	/**
	 * The constraints that failed. Shorthand for `$field->constraints->getFailed()`.
	 */
	public function getFailedConstraints(): Field\ConstraintResults
	{
		return $this->constraints->getFailed();
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
	 * @var list<string>
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
			throw InvalidConstraint::lookedUpWithNoName();
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
				throw InvalidConstraint::nameIsUsedTwiceOnAField($result->name, (string) $this->field->name);
			}

			$seen[$result->name] = true;
		}
	}
}
