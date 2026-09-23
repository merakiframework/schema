<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Field\ConstraintValidationResult;
use Meraki\Schema\Field\ShapeValidationResult;
use Meraki\Schema\Field\Definition;
use Meraki\Schema\PrefillPolicy;
use Meraki\Schema\ValueSource;

/**
 * A field holding one value, checked against its own constraints.
 *
 * This is the ordinary lifecycle, and nearly every field wants it: normalise what was submitted,
 * fall back to the authored default if nothing usable was, check the shape, then check the
 * constraints. A field whose value is a *list* resolves differently — see {@see Field\Collection},
 * which implements {@see Field} directly and shares this class's configuration through
 * {@see Definition} rather than inheriting a lifecycle that does not fit.
 *
 * Sealed by the language rather than by convention. `readonly` is inherited both ways — a
 * non-readonly class cannot extend this one — so every field below it is immutable whether or not
 * its author thought about it. Configuration is applied by handing back a modified copy, never by
 * writing to the field, because one schema serves many concurrent requests.
 *
 * Note that `readonly` is shallow: it stops a property being reassigned, not the object it holds
 * being mutated. Anything stored on a field must be immutable itself.
 *
 * @template AcceptedType of mixed = mixed
 * @implements Field<AcceptedType>
 */
abstract readonly class AtomicField implements Field
{
	use Definition;

	/**
	 * Sub-classes promote their own `$name` — the interface requires it, so it is checked at
	 * compile time without this class needing one of its own to pass down.
	 *
	 * They must still call this constructor, or `$optional` and `$defaultValue` stay uninitialised
	 * and throw on first read.
	 */
	public function __construct()
	{
		$this->initialiseDefinition();
	}

	/**
	 * Resolves a submitted value against this field, without checking it.
	 *
	 * Nothing is written back, so the field is unchanged and safe to share — resolving the same
	 * field concurrently with different values cannot interfere.
	 *
	 * The result is {@see ValidationStatus::Pending}: a form is rendered before it is submitted,
	 * and that state needs a name.
	 *
	 * @param AcceptedType|null $given exactly what was submitted, or null if nothing was
	 * @param list<Rule\AppliedOutcome> $appliedOutcomes rules that altered this field
	 */
	public function resolve(
		mixed $given,
		array $appliedOutcomes = [],
		ValueSource $givenAs = ValueSource::Submitted,
	): AggregatedValidationResult {
		return new ResolvedField(
			$this,
			$given,
			$this->resolvedValueFor($given),
			$appliedOutcomes,
			$this->sourceOf($given, $givenAs),
			$this->evaluatedAt(),
		);
	}

	/**
	 * Which of the three things the judged value actually was.
	 *
	 * Only this field can finish the answer: the caller knows whether it handed over something
	 * submitted or something prefilled, and the field knows whether its own default stood in when
	 * the caller handed over nothing.
	 */
	protected function sourceOf(mixed $given, ValueSource $givenAs): ValueSource
	{
		if ($given !== null) {
			return $givenAs;
		}

		return $this->defaultValue === null ? ValueSource::None : ValueSource::Default;
	}

	/**
	 * Settles absence, then reads the input exactly once.
	 *
	 * `$raw` is what there was to read — the submission, or the authored default standing in for
	 * it — and `null` means there was nothing. `$parsed` is what {@see Definition::parse()} made
	 * of it, and `null` there means it could not be read. The two nulls are different facts, which
	 * is why they are carried separately rather than collapsed into one value.
	 *
	 * @return array{mixed, mixed}
	 */
	private function read(mixed $given): array
	{
		$raw = $given ?? $this->defaultValue;

		return [$raw, $raw === null ? null : self::readable($this->parse(...), $raw)];
	}

	/**
	 * @param AcceptedType|null $given
	 * @param list<Rule\AppliedOutcome> $appliedOutcomes
	 */
	public function validate(
		mixed $given,
		array $appliedOutcomes = [],
		ValueSource $givenAs = ValueSource::Submitted,
		PrefillPolicy $policy = PrefillPolicy::Checked,
	): AggregatedValidationResult {
		// Read once here rather than through resolve(), which would parse twice.
		[$raw, $parsed] = $this->read($given);
		$source = $this->sourceOf($given, $givenAs);

		// `$parsed`, not `$parsed ?? $raw`: a value is what the field made of the input, and `null`
		// when it could make nothing of it. What was sent is on `$given`.
		return (new ResolvedField($this, $given, $parsed, $appliedOutcomes, $source, $this->evaluatedAt()))
			->withResults(...$this->check($raw, $parsed, $source, $policy));
	}

	/**
	 * Evaluates this field's shape and constraints against an already-resolved value.
	 *
	 * Shape first: if there is no usable value, the constraints have nothing to speak to and are
	 * skipped rather than failed, so an error report names the real problem once instead of once
	 * per constraint.
	 *
	 * @param mixed $raw what there was to read, or null when there was nothing
	 * @param mixed $parsed what parse() made of it, or null when it could not be read
	 * @param ValueSource $source where the judged value came from
	 * @param PrefillPolicy $policy whether a prefilled value still has to satisfy the constraints
	 * @return list<ConstraintValidationResult|ShapeValidationResult>
	 */
	protected function check(
		mixed $raw,
		mixed $parsed,
		ValueSource $source = ValueSource::Submitted,
		PrefillPolicy $policy = PrefillPolicy::Checked,
	): array {
		if ($raw === null) {
			// Nothing was submitted and no default stood in. Only acceptable when the field says so.
			$shape = $this->optional
				? ShapeValidationResult::skip()
				: ShapeValidationResult::missing();

			return [$shape, ...$this->constraints->allSkipped()];
		}

		if ($parsed === null) {
			// Something arrived and could not be read as this kind of value. The constraints have
			// nothing to speak to, so they are skipped rather than failed — one report for one
			// mistake, naming the real problem.
			//
			// Distinct from the branch above, and not merely for tidiness: "this is required" and
			// "this is not a valid duration" are different sentences, and a consumer that cannot
			// tell them apart has to go back to inspecting the submitted value to guess.
			return [ShapeValidationResult::unreadable(), ...$this->constraints->allSkipped()];
		}

		// A value the application vouches for, which the user was never asked about and could not
		// fix. The shape still had to pass to get here — trust says a value meets the rules, not
		// that the field can read it — but the rules themselves are waived.
		if ($source === ValueSource::Prefilled && $policy === PrefillPolicy::Trusted) {
			return [ShapeValidationResult::pass(), ...$this->constraints->allSkipped()];
		}

		// The parsed value goes straight to the constraints, which is what makes their parameter
		// types honest: `checkMinValue(Number\Value $value)` is given one by construction
		// rather than hoping a gate ran first.
		//
		// It is also what enforces parse()'s totality. Every accepted value in every test passes
		// through this line, so a parse that raises fails the test that submitted the value —
		// which covers far more ground than any central list of awkward values could, and cannot
		// fall out of step with the fields.
		return [
			ShapeValidationResult::pass(),
			...$this->constraints->against($parsed),
		];
	}


}
