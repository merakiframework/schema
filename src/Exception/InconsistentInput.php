<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use Meraki\Schema\Exception;
use LogicException;

/**
 * An input reported nothing wrong and still made no value. The field that read it has a bug.
 *
 * A {@see \Meraki\Schema\Field\Input} makes its value exactly when it reports no violations. This
 * is raised rather than reported, for two reasons.
 *
 * - **Somebody submitting a form cannot cause it.** Every input reaches the same line of the
 *   field's own code, so the fault is in that code.
 * - **A verdict would have nothing to say.** An "incomplete" result with no violation behind it
 *   would mark no box and give no sentence, and the person filling in the form could never get
 *   past it.
 */
final class InconsistentInput extends LogicException implements Exception
{
	/** @param class-string $input */
	public static function madeNoValueAndReportedNothing(string $input): self
	{
		return new self(sprintf(
			'%s reported no violations and made no value. An input makes its value exactly when '
				. 'nothing stands in the way, and says what does when something does.',
			$input,
		));
	}
}
