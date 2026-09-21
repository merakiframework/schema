<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Rule;

/**
 * A record that a rule matched and changed a field.
 *
 * Without this, a consumer that needs to know *why* a field is optional has to re-evaluate
 * every rule itself — which is exactly what `meraki/schema-html` did, in a
 * `deriveRuleEffects()` method that duplicated the engine in the presentation layer. The
 * answer belongs on the result.
 */
final class AppliedOutcome
{
	public function __construct(
		/** The rule that matched. */
		public readonly Rule $rule,
		/** The outcome it applied. */
		public readonly Outcome $outcome,
		/**
		 * Whether the rule's condition held.
		 *
		 * `false` means this came from the else-branch, which is still something the rule
		 * *did* — a consumer asking why a field is optional wants the answer either way. Only
		 * the branch differs, and a renderer explaining the rule needs to know which.
		 */
		public readonly bool $conditionMatched = true,
	) {
	}

	/**
	 * Whether this was applied by the given kind of outcome, e.g.
	 * `$applied->is(Outcome\Ignore::class)`, and `$applied->outcome->changes` for what a
	 * {@see Outcome\Reconfigure} actually set.
	 *
	 * @param class-string<Outcome> $outcome
	 */
	public function is(string $outcome): bool
	{
		return $this->outcome instanceof $outcome;
	}
}
