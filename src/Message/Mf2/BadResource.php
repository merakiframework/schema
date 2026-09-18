<?php
declare(strict_types=1);

namespace Meraki\Schema\Message\Mf2;

use InvalidArgumentException;

/**
 * A `.mfr` file that could not be read as one.
 *
 * Separate from {@see BadMessage} because the two are found by different people at different
 * times: a bad resource is a broken *file* — a line that is not `key = message`, a missing
 * `@locale`, the same key written twice — and it is found the moment the file is opened. A bad
 * message is a broken *sentence* inside a file that parsed.
 *
 * Both carry enough to fix the problem without opening a debugger: this one names the file and the
 * line.
 */
final class BadResource extends InvalidArgumentException
{
	private function __construct(
		string $message,
		public readonly string $origin,
		/** Which line of the resource, or null when the problem is with the file as a whole. */
		public readonly ?int $sourceLine,
	) {
		parent::__construct($message);
	}

	public static function at(string $origin, int $line, string $why): self
	{
		return new self(sprintf('%s:%d: %s', $origin, $line, $why), $origin, $line);
	}

	public static function in(string $origin, string $why): self
	{
		return new self(sprintf('%s: %s', $origin, $why), $origin, null);
	}
}
