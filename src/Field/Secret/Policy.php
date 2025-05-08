<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Secret;

use Stringable;

/**
 * @property-read string $name Unique name for this policy (e.g., 'password', 'passphrase')
 */
interface Policy extends Stringable
{
	// public string $name { get; }

	/**
	 * Check if this policy applies to the given value.
	 */
	public function matches(string $value): bool;

	public static function parse(string $spec): self;
}
