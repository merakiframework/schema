<?php
declare(strict_types=1);

namespace Meraki\Schema;

enum ValidationStatus: int
{
	case Passed = 0;
	case Pending = 1;
	case Skipped = 2;
	case Failed = 3;

	/**
	 * Predicates so a status reads as an answer rather than a comparison — `$result->shape->failed()`
	 * instead of `$result->shape === ValidationStatus::Failed`.
	 *
	 * Methods rather than properties because an enum cannot hold one; see docs/CODING-STYLE.md.
	 */
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

	/** Nothing has judged this yet — a form rendered but not submitted. */
	public function pending(): bool
	{
		return $this === self::Pending;
	}
}
