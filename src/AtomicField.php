<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\Field\ConstraintValidationResult;
use Meraki\Schema\Field\Definition;
use Meraki\Schema\Field\Input;
use Meraki\Schema\Field\ParsedValue;
use Meraki\Schema\Field\ShapeValidationResult;

/**
 * A field holding one value, checked against its own constraints.
 *
 * This is the ordinary lifecycle, and nearly every field wants it: normalise what was submitted,
 * fall back to the authored default if nothing usable was, check the shape, assemble a value from
 * its parts if it has any, then check the constraints. A field whose value is a *list* resolves
 * differently — see {@see Field\Collection}, which implements {@see Field} directly and shares this
 * class's configuration through {@see Definition} rather than inheriting a lifecycle that does not
 * fit.
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
	 * Settles absence, then reads the input exactly once.
	 *
	 * `$raw` is what there was to read — the submission, or the authored default standing in for
	 * it — and `null` means there was nothing. `$read` is what {@see Definition::parse()} made
	 * of it: a value, the {@see Input} a value is assembled from, or `null` when it could not be
	 * read. The two nulls are different facts, which is why they are carried separately rather
	 * than collapsed into one value.
	 *
	 * Protected so a field that overrides {@see self::validate()} only to return a richer result
	 * reads the way every other field does, rather than keeping a copy of this.
	 *
	 * @return array{mixed, ParsedValue|Input|null}
	 */
	final protected function read(mixed $given): array
	{
		$raw = $this->rawFor($given);

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
		[$raw, $read] = $this->read($given);
		$source = $this->sourceOf($given, $givenAs);

		// The assembled value, not `$read ?? $raw`: a value is what the field made of the input,
		// and `null` when it could make nothing of it — including parts that make no value. What
		// was sent is on `$given`.
		return (new ResolvedField($this, $given, self::valueFrom($read), $appliedOutcomes, $source, $this->evaluatedAt()))
			->withResults(...$this->check($raw, $read, $source, $policy));
	}

	/**
	 * Evaluates this field's shape and constraints against an already-resolved value.
	 *
	 * Shape first: if there is no usable value, the constraints have nothing to speak to and are
	 * skipped rather than failed, so an error report names the real problem once instead of once
	 * per constraint. In order:
	 *
	 * 1. nothing to read: *missing*, or skipped when the field is optional;
	 * 2. nothing readable: *unreadable*;
	 * 3. parts that make no value: *incomplete*, reported part by part;
	 * 4. a prefill the application vouches for: passed, with the constraints waived;
	 * 5. otherwise the constraints judge the value.
	 *
	 * @param mixed $raw what there was to read, or null when there was nothing
	 * @param ParsedValue|Input|null $read what parse() made of it, or null when it could not be read
	 * @param ValueSource $source where the judged value came from
	 * @param PrefillPolicy $policy whether a prefilled value still has to satisfy the constraints
	 * @return list<ConstraintValidationResult|ShapeValidationResult>
	 */
	protected function check(
		mixed $raw,
		ParsedValue|Input|null $read,
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

		if ($read === null) {
			// Something arrived and could not be read as this kind of value. The constraints have
			// nothing to speak to, so they are skipped rather than failed — one report for one
			// mistake, naming the real problem.
			//
			// Distinct from the branch above, and not merely for tidiness: "this is required" and
			// "this is not a valid duration" are different sentences, and a consumer that cannot
			// tell them apart has to go back to inspecting the submitted value to guess.
			return [ShapeValidationResult::unreadable(), ...$this->constraints->allSkipped()];
		}

		if ($read instanceof Input && $read->violations !== []) {
			// Parts that make no value. Every constraint is skipped, because none of them is
			// written for half a value — that is the point of assembling one first. The parts'
			// own violations are the report, each against the box it is about.
			//
			// "Every" is today's answer, not the promise. What is promised is that a constraint
			// that cannot be judged yet is skipped, so one that declares the parts it reads can run
			// here once those are sound — decided for 2.1, see docs/ROADMAP.md.
			return [
				ShapeValidationResult::incomplete($read->violations, $read->missingParts),
				...$this->constraints->allSkipped(),
			];
		}

		$value = self::valueFrom($read);

		// A value the application vouches for, which the user was never asked about and could not
		// fix. The shape still had to pass to get here, and so did assembly — trust says a value
		// meets the rules, not that the field can read it or that its parts make one — but the
		// rules themselves are waived.
		if ($source === ValueSource::Prefilled && $policy === PrefillPolicy::Trusted) {
			return [ShapeValidationResult::pass(), ...$this->constraints->allSkipped()];
		}

		// The value goes straight to the constraints, which is what makes their parameter types
		// honest: `checkMinValue(Number\Value $value)` is given one by construction rather than
		// hoping a gate ran first. For a value made of parts it is the assembled one, so a
		// constraint on money never meets an amount with no currency.
		//
		// It is also what enforces parse()'s totality. Every accepted value in every test passes
		// through this line, so a parse that raises fails the test that submitted the value —
		// which covers far more ground than any central list of awkward values could, and cannot
		// fall out of step with the fields.
		return [
			ShapeValidationResult::pass(),
			...$this->constraints->against($value),
		];
	}
}
