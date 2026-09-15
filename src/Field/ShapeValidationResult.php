<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\ValidationResult;
use Meraki\Schema\ValidationStatus;

/**
 * Whether a value could be read as the kind of thing a field holds, at all.
 *
 * This used to be reported as a constraint named `type`, which it never was. A constraint judges a
 * value the field already understands; this is the gate that decides whether any of them get to
 * run. Conflating them meant `getFailed()` returned a mix of "that is not a date" and "that date
 * is too early" — different kinds of answer, needing different messages — and it reserved the name
 * `type`, so no field could ever have a real constraint called that.
 *
 * It is still one of the results a {@see \Meraki\Schema\ResolvedField} holds, deliberately. Every
 * aggregate predicate — `anyFailed()`, `allPassed()`, `status` — derives from that list, so
 * keeping it there means none of them can forget the shape. Pulling it out into a property beside
 * the list would have left eight methods to remember to fold it back into, and the one that got
 * missed would have reported an unreadable value as fine.
 *
 * What it is *excluded* from is the constraint-facing API:
 * {@see \Meraki\Schema\ResolvedField::forConstraint()} and `$constraintNames` skip it, because it
 * is not one.
 *
 * ### Failing says which way it failed
 *
 * A failure carries a {@see ShapeProblem}: `Missing` when nothing was submitted for a field that
 * required something, `Unreadable` when something arrived that could not be read. These need
 * different sentences, and the only way to tell them apart used to be inspecting `$given` at the
 * call site.
 */
final class ShapeValidationResult implements ValidationResult
{
	private function __construct(
		public readonly ValidationStatus $status,
		/** Why it failed, or `null` when it did not. */
		public readonly ?ShapeProblem $problem = null,
	) {
	}

	/** The value was read as this field's kind of thing. */
	public static function pass(): self
	{
		return new self(ValidationStatus::Passed);
	}

	/** Nothing arrived, nothing stood in for it, and the field required one. */
	public static function missing(): self
	{
		return new self(ValidationStatus::Failed, ShapeProblem::Missing);
	}

	/** Something arrived and could not be read as this field's kind of thing. */
	public static function unreadable(): self
	{
		return new self(ValidationStatus::Failed, ShapeProblem::Unreadable);
	}

	/** Nothing arrived and the field said that was acceptable. */
	public static function skip(): self
	{
		return new self(ValidationStatus::Skipped);
	}

	/** Nothing has been checked yet — a field resolved for rendering rather than validated. */
	public static function pending(): self
	{
		return new self(ValidationStatus::Pending);
	}

	public function passed(): bool
	{
		return $this->status->passed();
	}

	public function failed(): bool
	{
		return $this->status->failed();
	}

	public function skipped(): bool
	{
		return $this->status->skipped();
	}

	/**
	 * Whether the field required a value and got none.
	 *
	 * There is deliberately no `pending()` predicate to match the factory of that name: nothing
	 * asks it, and `$shape->status->pending()` says it without a fourth near-identical method.
	 */
	public function wasMissing(): bool
	{
		return $this->problem === ShapeProblem::Missing;
	}

	/** Whether something arrived that could not be read. */
	public function wasUnreadable(): bool
	{
		return $this->problem === ShapeProblem::Unreadable;
	}
}
