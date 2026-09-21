<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule\Condition;

use Meraki\Schema\Facade;
use Meraki\Schema\Scope;

/**
 * Holds when the value sits within both bounds.
 *
 * **Inclusive at both ends**, and not arbitrarily so: it *is* {@see IsAtLeast} and {@see IsAtMost}
 * together, holding one of each and asking both. So the inclusivity is inherited rather than
 * chosen, and cannot drift from theirs — which prose alone could not promise. The parameters are
 * named to match: `isBetween(18, 65)` accepts both 18 and 65.
 *
 *     $age->when()->isBetween(18, 65)->then($guardianConsent->makeOptional());
 *
 * Worth saying why it is not simply `allOf(isAtLeast, isAtMost)` even though it means the same
 * thing. A range is one idea: an author writing two conditions has two places to edit and two
 * chances to leave them inconsistent. It also serialises as one condition carrying two bounds,
 * which is what a client-side translation of the rule wants to read.
 *
 * Both bounds go through {@see Comparison}'s reading, so either may be a literal or another
 * {@see Scope} — `isBetween(ValueScope::of('opens'), ValueScope::of('closes'))` is a rule about
 * three fields.
 */
final class IsBetween extends Comparison
{
	private readonly IsAtLeast $floor;

	private readonly IsAtMost $ceiling;

	public function __construct(
		Scope|string $target,
		mixed $atLeast,
		public readonly mixed $atMost,
	) {
		parent::__construct($target, $atLeast);

		$this->floor = new IsAtLeast($this->scope, $atLeast);
		$this->ceiling = new IsAtMost($this->scope, $atMost);
	}

	/**
	 * The lower bound, under the name the author used.
	 *
	 * `$expected` is what {@see Comparison} calls the first operand and what serialisation anchors
	 * on, so it stays. This is the same value said in the vocabulary of a range, because
	 * `$between->expected` reads as though a range had one bound.
	 */
	public mixed $atLeast {
		get => $this->expected;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function matches(array $data, Facade $schema): bool
	{
		// Short-circuit deliberately, and not only for speed: a value below the floor is out of
		// range whatever the ceiling says, and asking the second question anyway would raise on a
		// currency mismatch that the first had already settled.
		return $this->floor->matches($data, $schema) && $this->ceiling->matches($data, $schema);
	}

	/**
	 * Whichever bound is the problem, reported as that bound's own complaint.
	 *
	 * Delegated rather than restated so that a range on a field with no order gets the same
	 * sentence {@see Ordered} would have given for a plain `isAtLeast` — one explanation of what
	 * ordering means, in one place.
	 */
	public function whyItCouldNeverHold(Facade $schema): ?string
	{
		return $this->floor->whyItCouldNeverHold($schema) ?? $this->ceiling->whyItCouldNeverHold($schema);
	}

	/**
	 * @return list<mixed>
	 */
	protected function expectations(): array
	{
		return [$this->expected, $this->atMost];
	}
}
