<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use Meraki\Schema\Exception;
use UnexpectedValueException;

/**
 * The addressing library described a country's format in terms this library cannot read.
 *
 * `AddressFormat::getRequiredFields()` and `getUsedFields()` are documented as returning
 * `AddressField` objects and in practice return the strings those objects' constants hold.
 * {@see \Meraki\Schema\Field\Address\Requirements} reads either, plus any backed enum or
 * `Stringable` a future release might switch to, and raises this for anything else.
 *
 * It fires loudly for the reason {@see UnknownAddressPart} does, and the stakes are higher: a
 * comparison that silently stops matching would empty `requiredParts`, and an address would
 * never again be incomplete. `composer.json` allows `^2.0`, so an update inside the permitted
 * range could do it, and nothing else would go red.
 */
final class UnreadableAddressFormat extends UnexpectedValueException implements Exception
{
	public static function fieldIsNotReadable(mixed $field): self
	{
		return new self(sprintf(
			'The addressing library described a field as %s, which this library cannot read as a '
				. 'name. It handles a string, a backed enum and a Stringable. This is an upstream '
				. 'change rather than anything a submitter did.',
			get_debug_type($field),
		));
	}
}
