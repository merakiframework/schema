<?php
declare(strict_types=1);

namespace Meraki\Schema\Exception;

use Meraki\Schema\Exception;
use InvalidArgumentException;

/**
 * A part name that no address has.
 *
 * Nothing a submitter sends can raise this: an unknown key in a submitted record is a shape
 * failure long before a part name reaches the ladder. It fires when this library asks about a
 * part it invented — a typo in the map from the addressing library's field names to ours — and
 * it fires loudly on purpose, because the quiet alternative is answering "not required" and
 * letting the part go unchecked.
 */
final class UnknownAddressPart extends InvalidArgumentException implements Exception
{
	/**
	 * @param list<string> $known
	 */
	public static function notOnTheLadder(string $part, array $known): self
	{
		return new self(sprintf(
			'"%s" is not a part of an address. The parts are: "%s".',
			$part,
			implode('", "', $known),
		));
	}
}
