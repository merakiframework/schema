<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use LogicException;
use Meraki\Schema\Exception;

/**
 * A rule that is not finished being written.
 *
 * A `LogicException` where {@see InvalidRule} is an `InvalidArgumentException`, and the split is
 * the ordinary one: these are not bad *arguments*, they are a draft asked for something it is not
 * ready to give. Nothing about the values involved is wrong — the sequence of calls is.
 */
final class IncompleteRule extends LogicException implements Exception
{
	public static function saysNothingHappens(): self
	{
		return new self(
			'A rule must say what happens: attach an outcome with then() or else() before '
			. 'adding it to a schema.',
		);
	}

	/**
	 * `build()` asked for a rule whose outcomes are still fields waiting to be compared against
	 * the ones on the schema — which only `addRule()` can see.
	 */
	public static function needsTheSchemaToResolveAnOutcome(string $field): self
	{
		return new self(sprintf(
			'The outcome for "%s" is a field to be compared against the one on the schema, '
			. 'which this draft cannot see. Add the rule with $schema->addRule() instead of '
			. 'building it here.',
			$field,
		));
	}
}
