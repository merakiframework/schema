<?php
declare(strict_types=1);

namespace Meraki\Schema;

enum ValidationStatus: int
{
	case Passed = 0;
	case Pending = 1;
	case Skipped = 2;
	case Failed = 3;

	public function passed(): bool
	{
		return $this === self::Passed;
	}

	public function failed(): bool
	{
		return $this === self::Failed;
	}

	public function skipped(): bool
	{
		return $this === self::Skipped;
	}

	public function pending(): bool
	{
		return $this === self::Pending;
	}
}
