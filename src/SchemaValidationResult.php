<?php
declare(strict_types=1);

namespace Meraki\Schema;

/**
 * The result of validating a whole schema: an aggregate of one result per field.
 *
 * Status roll-up is intentionally left to the caller. Use the granular predicates
 * inherited from {@see AggregatedValidationResult} — e.g. `anyFailed()`,
 * `allPassed()`, `anyPending()` — to decide what "valid" means for your use case.
 */
final class SchemaValidationResult extends AggregatedValidationResult
{
	/**
	 * One field's outcome, by name.
	 *
	 * A structured field resolves to a result per part, so this returns the aggregate for
	 * those; ask it for a part by its qualified name — `price.amount`, not `amount`.
	 */
	public function get(string $name): ResolvedField|Field\CompositeValidationResult|null
	{
		foreach ($this->results as $result) {
			if ($result instanceof ResolvedField && (string) $result->field->name === $name) {
				return $result;
			}

			if ($result instanceof Field\CompositeValidationResult && (string) $result->composite->name === $name) {
				return $result;
			}
		}

		return null;
	}
}
