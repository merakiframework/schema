<?php
declare(strict_types=1);

namespace Meraki\Schema;

interface ValidationResult
{
	public ValidationStatus $status { get; }
}
