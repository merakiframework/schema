<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Brick\DateTime\Instant;

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
	 * The instant this request was judged at, read once from the schema's clock.
	 *
	 * Once, deliberately. Every field could read the clock itself, and under a `SystemClock` two
	 * of them would then get instants a few microseconds apart — harmless for a card expiry and
	 * not harmless for a rule that compares two time-relative fields to each other. One request
	 * gets one answer to "what time is it".
	 */
	public readonly Instant $evaluatedAt;

	public function __construct(Instant $evaluatedAt, ValidationResult ...$results)
	{
		$this->evaluatedAt = $evaluatedAt;

		parent::__construct(...$results);
	}

	/**
	 * One field's outcome, by name.
	 *
	 * Whatever shape that outcome takes: {@see ResolvedField} for a field holding one value, or
	 * something richer for one whose value has structure — {@see Field\Collection\Result} carries
	 * a verdict per item as well as the collection's own. Each says which field it belongs to, so
	 * this needs no knowledge of the shapes themselves.
	 */
	public function forField(string $name): ?FieldResult
	{
		foreach ($this->results as $result) {
			if ($result instanceof FieldResult && (string) $result->field->name === $name) {
				return $result;
			}
		}

		return null;
	}
}
