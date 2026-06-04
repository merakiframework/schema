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
}
