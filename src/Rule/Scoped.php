<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Scope;

/**
 * A condition that asks about one place.
 *
 * The three condition families — {@see Condition\Comparison}, {@see Condition\Emptiness} and
 * {@see Condition\Textual} — each hold a scope and always did; this only says so out loud, because
 * {@see Condition\Quantified} needs to re-root one and a bare {@see Condition} makes no such
 * promise. A group like {@see Condition\AllOf} deliberately does not implement it: it asks about
 * several places, so there is no single scope to re-root.
 */
interface Scoped
{
	/** What this condition asks about. */
	public Scope $scope { get; }

	/**
	 * The same condition, asking about somewhere else.
	 *
	 * The counterpart to {@see Scope::rootedAt()}, and what lets {@see Condition\Quantified} ask one
	 * row's question without knowing which condition it is holding — the operands differ by
	 * subclass, one for `equals` and a list for `isIn`, and only the scope is changing.
	 *
	 * Implemented by each family rather than by the caller, because a `readonly` property can only
	 * be replaced through `clone with` from inside the class that declared it.
	 */
	public function about(Scope $scope): static;
}
