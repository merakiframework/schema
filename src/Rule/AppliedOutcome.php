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
	) {
	}

	/**
	 * Whether this was applied by the given kind of outcome, e.g.
	 * `$applied->is(Outcome\MakeOptional::class)`.
	 *
	 * @param class-string<Outcome> $outcome
	 */
	public function is(string $outcome): bool
	{
		return $this->outcome instanceof $outcome;
	}
}
