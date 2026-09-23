<?php
declare(strict_types=1);

namespace Meraki\Schema\Message\Mf2;

use InvalidArgumentException;
use Meraki\Schema\Exception;

/**
 * A language pack that is not where the provider was told to look.
 *
 * Raised when the pack is *registered*, not when a message is asked for, and that is the whole
 * point of it: a provider that shrugged at a missing directory would fall through to
 * {@see \Meraki\Schema\Message\Silence} on every request, and a form rendering with no messages
 * looks like a form whose fields all passed.
 *
 * It lives here rather than in `Meraki\Schema\Exception` for the same reason {@see BadResource}
 * and {@see BadMessage} do: packs are an MF2 idea. A provider that reads messages from a database
 * has nothing this could describe.
 */
final class NoSuchPack extends InvalidArgumentException implements Exception
{
	public static function atPath(string $path): self
	{
		return new self(sprintf('There is no language pack directory at "%s".', $path));
	}

	public static function packageIsNotInstalled(string $package): self
	{
		return new self(sprintf(
			'The language pack "%s" is not installed. Run: composer require %s',
			$package,
			$package,
		));
	}

	/**
	 * Composer knows the package but cannot say where it is — which happens for a metapackage, or
	 * for a replaced or provided name that nothing ever wrote to disk.
	 */
	public static function packageHasNoDirectory(string $package): self
	{
		return new self(sprintf('"%s" is installed but has no directory on disk.', $package));
	}
}
