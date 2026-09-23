<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use LogicException;
use Meraki\Schema\Exception;

/**
 * A schema was asked to validate when it has no fields.
 *
 * Deliberately not a validation failure. There is no input that could make an empty schema right,
 * so there is nothing to report back to whoever filled the form in — the mistake is in the code
 * that built the schema, and it belongs where that code can see it.
 */
final class NothingToValidate extends LogicException implements Exception
{
	public static function theSchemaHasNoFields(string $schema): self
	{
		return new self(sprintf(
			'The schema "%s" has no fields, so there is nothing to validate. Add at least one '
			. 'with add() before validating.',
			$schema,
		));
	}
}
