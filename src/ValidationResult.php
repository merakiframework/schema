<?php
declare(strict_types=1);

namespace Meraki\Schema;

/**
 * @property-read ValidationStatus $status
 */
interface ValidationResult
{
	public const PASSED = 0;
	public const SKIPPED = 1;
	public const FAILED = 2;

	public const PENDING = 3;

	/**
	 * Check if the validation result has passed.
	 */
	public function passed(): bool;

	/**
	 * Check if the validation result was was skipped.
	 */
	public function skipped(): bool;

	/**
	 * Check if the validation result has failed.
	 */
	public function failed(): bool;

	/**
	 * Check if the validation result is pending.
	 */
	public function pending(): bool;
}
