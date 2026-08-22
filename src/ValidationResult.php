<?php
declare(strict_types=1);

namespace Meraki\Schema;

interface ValidationResult
{
	/**
	 * Every result reports a status. Aggregates compute theirs from what they contain;
	 * a constraint result carries one directly.
	 */
	public ValidationStatus $status { get; }
}
