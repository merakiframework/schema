<?php
declare(strict_types=1);

namespace Meraki\Schema\Message\Mf2;

use InvalidArgumentException;
use Meraki\Schema\Exception;

/**
 * A message this formatter will not render, and why.
 *
 * Three causes, and they are deliberately one exception because the fix is the same in each case —
 * edit the pack:
 *
 * - **A feature that is not implemented.** MF2 has selection, functions, markup and literals; this
 *   formatter expands variables and nothing else. See {@see Formatter} for why refusing beats
 *   ignoring.
 * - **A malformed message.** An unbalanced brace, a stray `}`, an escape of something that is not
 *   escapable.
 * - **A variable the library does not supply.** A pack asking for `{$minimum}` when the library
 *   offers `{$bound}` renders nothing useful, so it is not allowed to render at all.
 *
 * All three are authoring mistakes rather than facts about a request, which is why they raise.
 * {@see PackValidator} exists so they raise in a pack's own build rather than on somebody's form:
 * the whole point of a data-only language pack is that the thing checking it is a program, and the
 * thing checking it should run before anybody installs it.
 */
final class BadMessage extends InvalidArgumentException implements Exception
{
	private function __construct(string $message, public readonly string $source)
	{
		parent::__construct($message);
	}

	/** Something MF2 has and this formatter does not. */
	public static function feature(string $feature, string $source): self
	{
		return new self(sprintf(
			'This formatter expands variables and nothing else, so it cannot render %s: %s. '
			. 'Rewrite the message without it, or wait for a full MF2 implementation.',
			$feature,
			self::excerpt($source),
		), $source);
	}

	/** Not valid MF2 at all. */
	public static function malformed(string $why, string $source): self
	{
		return new self(sprintf('%s: %s', $why, self::excerpt($source)), $source);
	}

	/**
	 * A variable nothing will ever put a value in.
	 *
	 * @param list<string> $available
	 */
	public static function unknownVariable(string $name, array $available, string $source): self
	{
		sort($available);

		return new self(sprintf(
			'Nothing supplies $%s here. Available: %s. In: %s',
			$name,
			$available === [] ? '(nothing)' : '$' . implode(', $', $available),
			self::excerpt($source),
		), $source);
	}

	private static function excerpt(string $source): string
	{
		return '"' . (strlen($source) > 60 ? substr($source, 0, 57) . '...' : $source) . '"';
	}
}
