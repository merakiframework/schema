<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field;
use Meraki\Schema\Property;
use InvalidArgumentException;

/**
 * A spacer with no meaning of its own. This is a presentation concern that predates the
 * split into separate rendering packages, and it is removed in 2.0 along with the rest of
 * the HTML-form legacy — but meraki/schema-json serializes it, so it stays for now.
 *
 * @extends Field<mixed|null>
 */
final class Placeholder extends Field
{
	public function __construct(
		public readonly Property\Name $name,
	) {
	}

	public function validateValue(mixed $value): bool
	{
		return is_string($value);
	}

	protected function getConstraints(): array
	{
		return [];
	}
}
