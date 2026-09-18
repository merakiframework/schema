<?php
declare(strict_types=1);

namespace Meraki\Schema\Message\Mf2;

use Meraki\Schema\Message\Vocabulary;

/**
 * Checks a language pack before anybody installs it.
 *
 * A pack is data with no PHP in it, so nothing about it is checked by a compiler. This is what
 * stands in: run it in the pack's own CI and a mistake fails a build rather than rendering to
 * somebody in a language nobody on the team reads.
 *
 *     composer require --dev meraki/schema
 *     vendor/bin/schema-lang validate .
 *
 * ### What it catches
 *
 * - A file that is not a valid resource — a line that is not `key = message`, a missing `@locale`,
 *   a key defined twice.
 * - A `@locale` that disagrees with the file name, which would make the file unreachable.
 * - A variant with no base: `en_AU.mfr` claims by its name to be a variation of `en`, and if there
 *   is no `en.mfr` that claim is false.
 * - A message using anything {@see Formatter} does not implement — selection, functions, markup.
 *   This is the check that keeps the swap to a real MF2 implementation safe.
 * - A key that is not part of {@see Vocabulary}: a typo, or wording for a constraint that no longer
 *   exists.
 * - A message naming a variable nothing will fill — `{$minimum}` where the library supplies
 *   `{$bound}`. This is the one a translator is most likely to write and the one that is hardest to
 *   notice, because the message reads perfectly until it runs.
 *
 * Coverage — which constraints a locale has no wording for at all — is reported separately by
 * {@see self::missing()}, because an incomplete pack is a useful pack and should not fail a build
 * unless its authors want it to.
 */
final class PackValidator
{
	public function __construct(private readonly Formatter $formatter = new Formatter())
	{
	}

	/**
	 * Everything wrong with a pack, as lines ready to print. Empty means it is sound.
	 *
	 * @return list<string>
	 */
	public function check(string $directory): array
	{
		if (!is_dir($directory)) {
			return [sprintf('There is no directory at "%s".', $directory)];
		}

		$files = glob(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . '*.mfr') ?: [];

		if ($files === []) {
			return [sprintf('"%s" holds no .mfr files, so it is not a language pack.', $directory)];
		}

		$problems = [];
		$tags = [];

		foreach ($files as $file) {
			$tag = self::normalise(basename($file, '.mfr'));
			$tags[$tag] = true;

			try {
				$resource = Resource::fromFile($file);
			} catch (BadResource $e) {
				$problems[] = $e->getMessage();
				continue;
			}

			if (self::normalise($resource->locale) !== $tag) {
				$problems[] = sprintf(
					'%s: @locale is "%s" but the file is named for "%s". Nothing would ever load it.',
					basename($file),
					$resource->locale,
					basename($file, '.mfr'),
				);
			}

			foreach ($resource->entries as $key => $message) {
				foreach ($this->checkEntry($key, $message) as $problem) {
					$problems[] = basename($file) . ': ' . $problem;
				}
			}
		}

		foreach (array_keys($tags) as $tag) {
			$parts = explode('_', $tag);

			// Only the immediate parent: a chain with a hole anywhere in it has a hole at some
			// single step, and naming that step is more useful than naming the whole chain.
			if (count($parts) > 1) {
				array_pop($parts);
				$base = implode('_', $parts);

				if (!isset($tags[$base])) {
					$problems[] = sprintf(
						'%s.mfr is a variation of "%s", but there is no %s.mfr for it to vary from.',
						$tag,
						$base,
						$base,
					);
				}
			}
		}

		sort($problems);

		return $problems;
	}

	/**
	 * What each locale has no wording for, once its base is merged in.
	 *
	 * A report rather than a verdict. Most packs will be missing something, and a pack that covers
	 * ninety constraints out of a hundred is worth shipping — the ten it misses leave a consumer
	 * with no sentence, which is the documented behaviour rather than a failure.
	 *
	 * @return array<string, list<string>> locale => the keys it says nothing for
	 */
	public function missing(string $directory): array
	{
		$provider = Mf2Provider::fromDirectory($directory);
		$wanted = self::wanted();
		$missing = [];

		foreach ($provider->locales as $locale) {
			$resource = $provider->merged($locale);

			if ($resource === null) {
				continue;
			}

			$absent = [];

			foreach ($wanted as $key) {
				if ($resource->get($key) === null) {
					$absent[] = $key;
				}
			}

			if ($absent !== []) {
				$missing[$locale] = $absent;
			}
		}

		return $missing;
	}

	/**
	 * The keys a pack needs for every failure to have *something* to say: the two shape problems,
	 * every constraint name at its generic rung, and a name for every kind and part a message can
	 * interpolate.
	 *
	 * Deliberately the generic rungs only. The specific ones — `Password.minLength` — exist so a
	 * pack can say something better, and not having a better thing to say is not a gap.
	 *
	 * @return list<string>
	 */
	private static function wanted(): array
	{
		$keys = [];

		foreach (Vocabulary::SHAPE_PROBLEMS as $problem) {
			$keys[] = "shape.{$problem}";
		}

		foreach (Vocabulary::constraintNames() as $name) {
			$keys[] = $name;
		}

		foreach (Vocabulary::kinds() as $kind) {
			$keys[] = "kind.{$kind}";
		}

		foreach (Vocabulary::partNames() as $part) {
			$keys[] = "part.{$part}";
		}

		return $keys;
	}

	/** @return list<string> */
	private function checkEntry(string $key, string $message): array
	{
		$allowed = Vocabulary::variablesFor($key);

		if ($allowed === null) {
			return [sprintf(
				'"%s" is not something this library asks for. Run "schema-lang keys" for the list.',
				$key,
			)];
		}

		try {
			$used = $this->formatter->variablesIn($message);
		} catch (BadMessage $e) {
			return [sprintf('"%s": %s', $key, $e->getMessage())];
		}

		$problems = [];

		foreach ($used as $variable) {
			if (!in_array($variable, $allowed, true)) {
				$problems[] = sprintf(
					'"%s" uses $%s, which nothing supplies here. Available: %s.',
					$key,
					$variable,
					$allowed === [] ? '(nothing)' : '$' . implode(', $', $allowed),
				);
			}
		}

		return $problems;
	}

	private static function normalise(string $tag): string
	{
		return strtolower(str_replace('-', '_', trim($tag)));
	}
}
