<?php
declare(strict_types=1);

namespace Meraki\Schema\Message\Mf2;

/**
 * One `.mfr` file: a locale, and the messages written for it.
 *
 * The message *resource* format is the container the MF2 working group is building around the
 * message syntax — `key = message`, `#` for comments, `@` for metadata, with `@locale` required.
 * It is still in progress, which is the one place this implementation is knowingly ahead of a
 * finished specification. The exposure is small and deliberate: the container is a dozen lines of
 * parsing, and the *messages* inside it — the part a translator writes, the part that is expensive
 * to redo — are MF2 proper and will not move.
 *
 * ### Deliberately line-based, with no continuations
 *
 * One message per line, and a line that is not a comment, a metadata entry or `key = message` is
 * an error naming its number. The working group's draft has more than this. Accepting only what is
 * certain means a pack written today cannot depend on something that changes, which is the same
 * argument {@see Formatter} makes for refusing features: leniency now is a migration later.
 *
 * ### Keys are looked up, not iterated
 *
 * A key is a path through a specificity ladder — `Address.postal_code.postalCodeFormat` down to
 * `postalCodeFormat` — and {@see Mf2Translator} walks it. Nothing reads these in order, so nothing
 * depends on the order they were written in.
 */
final class Resource
{
	private const KEY = '/^[A-Za-z_][A-Za-z0-9_.\-]*$/';

	/**
	 * @param string $locale the `@locale` this file declares, verbatim.
	 * @param array<string, string> $entries every `key = message`, unrendered. Parsing a message
	 *        is {@see Formatter}'s job and happens on use, so a pack with one unsupported entry
	 *        still serves the rest — a broken sentence should not take a language offline.
	 * @param array<string, string> $metadata every `@name = value`, including `locale`.
	 * @param string $origin the file this came from, for error messages.
	 */
	private function __construct(
		public readonly string $locale,
		public readonly array $entries,
		public readonly array $metadata,
		public readonly string $origin,
	) {
	}

	/**
	 * @throws BadResource if the file cannot be read or does not parse
	 */
	public static function fromFile(string $path): self
	{
		$source = @file_get_contents($path);

		if ($source === false) {
			throw BadResource::in($path, 'cannot be read.');
		}

		return self::parse($source, $path);
	}

	/**
	 * @param string $origin what to call this in an error, usually a file path
	 * @throws BadResource
	 */
	public static function parse(string $source, string $origin = '(string)'): self
	{
		$entries = [];
		$metadata = [];
		$lines = preg_split('/\r\n|\n|\r/', $source) ?: [];

		foreach ($lines as $index => $raw) {
			$number = $index + 1;
			$line = trim($raw);

			if ($line === '' || str_starts_with($line, '#')) {
				continue;
			}

			$at = str_starts_with($line, '@');
			$split = strpos($line, '=');

			if ($split === false) {
				throw BadResource::at($origin, $number, sprintf(
					'"%s" is not a comment, a "@name = value" or a "key = message".',
					$line,
				));
			}

			$key = trim(substr($line, 0, $split));
			$value = trim(substr($line, $split + 1));
			$name = $at ? substr($key, 1) : $key;

			if (preg_match(self::KEY, $name) !== 1) {
				throw BadResource::at($origin, $number, sprintf('"%s" is not a valid key.', $key));
			}

			// Not a warning. Two lines claiming one key means one of them does nothing, and which
			// one is invisible — a variant file is how a message gets overridden, and that is
			// visible because it is a different file.
			if (isset($entries[$name]) || ($at && isset($metadata[$name]))) {
				throw BadResource::at($origin, $number, sprintf('"%s" is already defined in this file.', $key));
			}

			if ($at) {
				$metadata[$name] = $value;
			} else {
				$entries[$name] = $value;
			}
		}

		if (!isset($metadata['locale']) || $metadata['locale'] === '') {
			throw BadResource::in($origin, 'has no "@locale". Every resource must say what language it is in.');
		}

		return new self($metadata['locale'], $entries, $metadata, $origin);
	}

	/** The message written for a key, or null if this file has none. */
	public function get(string $key): ?string
	{
		return $this->entries[$key] ?? null;
	}

	/**
	 * This resource with another laid over it, key by key.
	 *
	 * How a variant works: `en_AU` is `en` plus the handful of things Australia says differently,
	 * so it overrides `part.postal_code` and inherits the other two hundred entries untouched. The
	 * chain is derived from the file names by {@see Mf2Provider}, which is why nothing in the file
	 * has to declare what it extends.
	 */
	public function mergedWith(self $other): self
	{
		return new self(
			$other->locale,
			[...$this->entries, ...$other->entries],
			[...$this->metadata, ...$other->metadata],
			$other->origin,
		);
	}
}
