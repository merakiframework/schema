<?php
declare(strict_types=1);

namespace Meraki\Schema\Message\Mf2;

use Composer\InstalledVersions;
use Meraki\Schema\Message\Provider;
use Meraki\Schema\Message\Silence;
use Meraki\Schema\Message\Translator;

/**
 * Wording read from language packs on disk: directories of `.mfr` files, one per locale.
 *
 * ### A pack is data, and getting it here is not this library's job
 *
 * `meraki/schema-language-english` contains `en.mfr`, `en_AU.mfr` and nothing else — no PHP, no
 * autoloader, no class to extend. That is what makes the cross-implementation promise real: a Rust
 * or JavaScript port reads the same files, so a sentence is written once and rendered identically
 * everywhere.
 *
 * It is also a Composer package, which is why this takes **directories** rather than repositories.
 * Fetching, versioning, locking, integrity and caching are all things Composer already does, and
 * doing them here would mean a network call inside `validate()` — a latency spike, an offline
 * failure mode, and a supply chain where a moved repository silently changes what users read. An
 * unpublished or private pack is `{"type": "vcs"}` in `repositories`, which is the same
 * "register the git repo" step done where it belongs.
 *
 *     $provider = Mf2Provider::fromPackage('meraki/schema-language-english')
 *         ->withPack(__DIR__ . '/../resources/lang');   // your own wording, laid over the top
 *
 *     $schema = new Facade('signup', messages: $provider);
 *
 * Because it is only ever a path, a test points it at a fixture folder, which a provider that
 * fetched could not do.
 *
 * ### Variants come from the file names
 *
 * `en_AU` is resolved as `en` with `en_AU` laid over it, so an Australian pack overrides `postcode`
 * and inherits everything else. The chain is derived by dropping subtags — `zh_Hans_CN`, then
 * `zh_Hans`, then `zh` — and nothing in a file declares what it extends, because a file that named
 * its own parent could disagree with its name.
 *
 * Tags are matched case-insensitively and `-` and `_` are interchangeable, because a request
 * carries `en-AU` from `Accept-Language` and a file is conventionally named `en_AU.mfr`, and
 * nobody should have to care which is which.
 */
final class Mf2Provider implements Provider
{
	/** @var list<string> later packs override earlier ones */
	private array $packs = [];

	/** @var array<string, Resource>|null tag (normalised) => merged resource, built on first use */
	private ?array $index = null;

	private function __construct(private readonly Formatter $formatter = new Formatter())
	{
	}

	/**
	 * A pack in a directory: every `*.mfr` at its top level, named for the locale it holds.
	 *
	 * @throws NoSuchPack if the directory does not exist
	 */
	public static function fromDirectory(string $path): self
	{
		return (new self())->withPack($path);
	}

	/**
	 * A pack installed by Composer, found without hardcoding a path into `vendor/`.
	 *
	 * @throws NoSuchPack if the package is not installed
	 */
	public static function fromPackage(string $package): self
	{
		return (new self())->withPackage($package);
	}

	/** A provider with no packs yet — the starting point when every pack is added conditionally. */
	public static function empty(): self
	{
		return new self();
	}

	/**
	 * Adds a directory, overriding anything already registered.
	 *
	 * Later wins, so an application's own folder laid over a published pack replaces the entries it
	 * defines and inherits the rest. That is the same rule variants follow, one level up.
	 *
	 * @throws NoSuchPack if the directory does not exist
	 */
	public function withPack(string $path): self
	{
		$real = is_dir($path) ? realpath($path) : false;

		if ($real === false) {
			throw NoSuchPack::atPath($path);
		}

		$copy = clone $this;
		$copy->packs = [...$this->packs, $real];
		$copy->index = null;

		return $copy;
	}

	/**
	 * Adds a Composer package's install directory.
	 *
	 * @throws NoSuchPack if the package is not installed
	 */
	public function withPackage(string $package): self
	{
		if (!class_exists(InstalledVersions::class) || !InstalledVersions::isInstalled($package)) {
			throw NoSuchPack::packageIsNotInstalled($package);
		}

		$path = InstalledVersions::getInstallPath($package);

		if ($path === null) {
			throw NoSuchPack::packageHasNoDirectory($package);
		}

		return $this->withPack($path);
	}

	public function supports(string $locale): bool
	{
		return $this->merged($locale) !== null;
	}

	public function forLocale(string $locale): Translator
	{
		$resource = $this->merged($locale);

		return $resource === null
			? Silence::for(self::canonical($locale))
			: new Mf2Translator(self::canonical($resource->locale), $resource, $this->formatter);
	}

	/**
	 * Every locale any registered pack offers, canonically spelled and sorted.
	 *
	 * Useful for a content-negotiation step, and for telling somebody which languages they can ask
	 * for when the one they asked for is not there.
	 *
	 * @var list<string>
	 */
	public array $locales {
		get {
			$tags = array_map(self::canonical(...), array_keys($this->indexed()));
			sort($tags);

			return $tags;
		}
	}

	/**
	 * Everything a locale actually says, once its base and any overriding packs are folded in.
	 *
	 * Merging runs least specific first, so `en` is laid down and `en_AU` overrides it. Public
	 * because it is what {@see self::forLocale()} renders from and because a tool wants to ask it
	 * directly — {@see PackValidator} reports coverage against the merged result rather than
	 * against one file, since a variant inheriting a sentence is not missing it.
	 */
	public function merged(string $locale): ?Resource
	{
		$index = $this->indexed();
		$merged = null;

		foreach (self::chain($locale) as $tag) {
			if (!isset($index[$tag])) {
				continue;
			}

			$merged = $merged === null ? $index[$tag] : $merged->mergedWith($index[$tag]);
		}

		return $merged;
	}

	/**
	 * Every pack's files, parsed once and keyed by normalised tag.
	 *
	 * Parsing on first use rather than on registration keeps building a provider free, which
	 * matters because an application wires one up whether or not the request turns out to need it.
	 *
	 * @return array<string, Resource>
	 */
	private function indexed(): array
	{
		if ($this->index !== null) {
			return $this->index;
		}

		$index = [];

		foreach ($this->packs as $pack) {
			foreach (glob($pack . DIRECTORY_SEPARATOR . '*.mfr') ?: [] as $file) {
				$tag = self::normalise(basename($file, '.mfr'));
				$resource = Resource::fromFile($file);

				// A later pack overriding an earlier one is the point; overriding entry by entry
				// rather than file by file is what lets an application change one sentence.
				$index[$tag] = isset($index[$tag]) ? $index[$tag]->mergedWith($resource) : $resource;
			}
		}

		return $this->index = $index;
	}

	/**
	 * A tag and everything it inherits from, least specific first.
	 *
	 * `en_AU` gives `['en', 'en_au']`, so merging in order lays the base down before the variant.
	 *
	 * @return list<string>
	 */
	private static function chain(string $locale): array
	{
		$parts = explode('_', self::normalise($locale));
		$chain = [];
		$tag = '';

		foreach ($parts as $part) {
			$tag = $tag === '' ? $part : $tag . '_' . $part;
			$chain[] = $tag;
		}

		return $chain;
	}

	/** Lower case, underscore-separated: what tags are compared as, never displayed as. */
	private static function normalise(string $locale): string
	{
		return strtolower(str_replace('-', '_', trim($locale)));
	}

	/**
	 * BCP 47 spelling: `en-AU`, `zh-Hans-CN`. What a tag is reported as, never compared as.
	 *
	 * A file may be named either way — `en_AU.mfr` and `en-AU.mfr` both work — because the
	 * underscore is the gettext habit and the hyphen is the standard, and a translator should not
	 * have to know which one this library happened to pick.
	 */
	private static function canonical(string $locale): string
	{
		$parts = explode('_', self::normalise($locale));

		foreach ($parts as $i => $part) {
			if ($i === 0) {
				continue;
			}

			$parts[$i] = match (strlen($part)) {
				2 => strtoupper($part),
				4 => ucfirst($part),
				default => $part,
			};
		}

		return implode('-', $parts);
	}
}
