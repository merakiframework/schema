<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Validator\CheckType;

/**
 * @property-read string $name
 */
interface Type
{
	public function accepts(mixed $value): bool;

	public function getValidator(): CheckType;

	// public function isMissing(mixed $value): bool;

	// public function cast(mixed $value): mixed;

	// public function canonicalize(mixed $value): mixed;
}
