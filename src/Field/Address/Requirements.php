<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Address;

use Meraki\Schema\Exception\UnreadableAddressFormat;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;
use BackedEnum;
use Stringable;

/**
 * What one country asks of an address, as one field would judge it.
 *
 * Exactly the bounds that cannot be declared on the field, because each depends on which country
 * was submitted and the default field allows any of them. It invents no vocabulary: these are the
 * questions the constraints already ask, answered with the missing input supplied.
 *
 * A port needs this before a request. `$field->postalCodeRequired` could only ever answer while
 * one country was allowed, so the fields that most need the answer — a form with a country
 * dropdown — were the ones that could not get it. That gap is why `schema-html` ended up
 * re-implementing `isUsedByAny()` and `postalCodePatternFor()` against the same data, and
 * drifting from this library while it did.
 *
 * **Every property is public, and deliberately.** This object is meant to survive `json_encode`
 * so a consumer in another language can apply the same rules: anything hidden behind a method
 * would be a rule only PHP could follow. {@see self::postalCodeFormatFor()} is sugar over public
 * data, never the only way in — `overrides[subdivision] ?? postalCodeFormat` is one line in any
 * language.
 *
 * The constraints read the same answer, so the lookup and the checks cannot disagree.
 */
final readonly class Requirements
{
	/**
	 * Every part the addressing data can speak about, in the order an address is written.
	 *
	 * The name fields and `sortingCode` are absent because this library does not model them, and
	 * `organization` because an address identifies a place rather than who is at it.
	 *
	 * @var array<string, string> upstream field name => our part name
	 */
	private const PARTS = [
		'addressLine1' => 'street',
		'addressLine2' => 'street',
		'addressLine3' => 'street',
		'dependentLocality' => 'dependent_locality',
		'locality' => 'locality',
		'administrativeArea' => 'subdivision',
		'postalCode' => 'postal_code',
	];

	/**
	 * @param string $country the canonical ISO 3166-1 alpha-2 code, whatever spelling was asked
	 * @param list<string> $requiredParts what this country asks for, minus anything below the
	 *        field's precision floor. Never includes `country`: every address everywhere needs
	 *        one, so it is a fact about addresses rather than something a country asks for, and
	 *        the field reports its absence as `countryRequired` whatever the floor.
	 * @param list<string> $usedParts what an address here may have at all, floor or no floor
	 * @param array<string, string> $subdivisions full ISO 3166-2 code => name. Empty where a
	 *        country has none on file, which includes eight that use one without publishing a
	 *        list. The name is carried because it is a *matching key* as much as a label — an
	 *        address may name its state in full — so the mapping to the canonical code has to
	 *        be published rather than left for each consumer to rebuild.
	 * @param string|null $postalCodeFormat the pattern that applies, or null where a country has
	 *        no postcode at all
	 * @param array<string, string> $postalCodeFormatOverrides subdivisions whose own pattern
	 *        *replaces* the country's, keyed by full ISO 3166-2 code. Usually empty: 36 of 1548
	 *        subdivisions carry one, across China and Colombia alone. It replaces rather than
	 *        narrows, because `CN-TW` admits three to six digits where China admits exactly six.
	 * @param int $streetLineLimit how many lines a street may run to
	 */
	public function __construct(
		public string $country,
		public array $requiredParts,
		public array $usedParts,
		public array $subdivisions,
		public ?string $postalCodeFormat,
		public array $postalCodeFormatOverrides,
		public int $streetLineLimit,
	) {
	}

	/**
	 * What the given country asks of an address, seen through the given floor.
	 *
	 * @param string $countryCode a canonical alpha-2 code — the caller resolves the spelling
	 */
	public static function forCountry(string $countryCode, Precision $floor): self
	{
		// Keyed on the floor as well as the country: the two are what the answer depends on,
		// and a cache on the country alone would hand one field another's requiredness. Worth
		// having rather than leaving to the repositories — a field asks this roughly fifteen
		// times per request, once per constraint plus again for each `boundFor`, and the
		// subdivision list it rebuilds runs to sixty-odd entries for the United States.
		static $cache = [];

		$key = "{$countryCode}:{$floor->value}";

		if (isset($cache[$key])) {
			return $cache[$key];
		}

		$format = self::formats()->get($countryCode);
		$used = self::translate($format->getUsedFields());

		return $cache[$key] = new self(
			$countryCode,
			// The floor only ever declines to inherit a requirement; it never adds one. So a
			// Locality floor in Panama leaves `postal_code` in reach and Panama asks for no
			// postcode, and nothing requires one.
			array_values(array_filter(
				self::translate($format->getRequiredFields()),
				static fn(string $part): bool => $floor->covers($part),
			)),
			$used,
			self::subdivisionsIn($countryCode),
			$format->getPostalCodePattern(),
			self::postalCodeOverridesIn($countryCode),
			self::lineLimitOf($format->getUsedFields()),
		);
	}

	/**
	 * The postcode pattern that applies, given what is known about the subdivision.
	 *
	 * Pass nothing while no subdivision has been chosen — a form that has not reached that field
	 * yet — and the country's pattern comes back. Pass one in any spelling it would accept as
	 * input, and its own pattern comes back where it has one.
	 *
	 * Sugar over {@see self::$postalCodeFormat} and {@see self::$postalCodeFormatOverrides},
	 * which are public precisely so a consumer that cannot call this can still apply the rule.
	 */
	public function postalCodeFormatFor(?string $subdivision = null): ?string
	{
		if ($subdivision === null) {
			return $this->postalCodeFormat;
		}

		$code = $this->subdivisionCodeFor($subdivision);

		// A subdivision this country does not have answers to the country's pattern rather than
		// to nothing: `knownSubdivision` is what reports the subdivision itself.
		if ($code === null) {
			return $this->postalCodeFormat;
		}

		return $this->postalCodeFormatOverrides[$code] ?? $this->postalCodeFormat;
	}

	/**
	 * The full ISO 3166-2 code for a subdivision written any way this country accepts it — a
	 * bare code, the full code, or its name, in any case.
	 *
	 * Published rather than kept private because the library accepts a full name as input, so a
	 * consumer holding one needs the same mapping to reach anything keyed by code.
	 */
	public function subdivisionCodeFor(string $subdivision): ?string
	{
		return self::subdivisionCodeIn($this->country, $subdivision);
	}

	/**
	 * @see self::subdivisionCodeFor() — the static form, for a caller with no instance in hand
	 */
	public static function subdivisionCodeIn(string $countryCode, string $subdivision): ?string
	{
		$prefix = "{$countryCode}-";
		$candidate = trim($subdivision);

		// Only *this* country's prefix comes off: `US-CA` on an Australian address names
		// nothing, and should not quietly become `AU-CA`. Case-insensitively, because five
		// countries — CV, HK, KY, RU and TV — code their subdivisions by name, so the code
		// behind the prefix carries its own capitalisation.
		if (mb_strtolower(mb_substr($candidate, 0, mb_strlen($prefix))) === mb_strtolower($prefix)) {
			$candidate = mb_substr($candidate, mb_strlen($prefix));
		}

		$folded = mb_strtolower($candidate);

		// One pass over both spellings. An exact-key lookup cannot match a key that is a name
		// with its own capitalisation, which is how the library came to refuse the very codes it
		// publishes for Hong Kong.
		foreach (self::subdivisionsIn($countryCode) as $code => $name) {
			$bare = mb_substr($code, mb_strlen($prefix));

			if (mb_strtolower($bare) === $folded || mb_strtolower($name) === $folded) {
				return $code;
			}
		}

		return null;
	}

	/**
	 * A country's subdivisions, as ISO 3166-2 writes them, mapped to their names.
	 *
	 * The addressing data stores the suffix alone — `QLD` — because the country is implied by
	 * the lookup. Written out in full here so the value is unambiguous on its own, which matters
	 * when a rule compares this part across fields or a consumer reads it without the country
	 * beside it.
	 *
	 * @return array<string, string>
	 */
	public static function subdivisionsIn(string $countryCode): array
	{
		static $cache = [];

		if (isset($cache[$countryCode])) {
			return $cache[$countryCode];
		}

		$subdivisions = [];

		foreach (self::subdivisions()->getList([$countryCode]) as $code => $name) {
			$subdivisions["{$countryCode}-{$code}"] = $name;
		}

		return $cache[$countryCode] = $subdivisions;
	}

	/**
	 * The subdivisions of a country whose own postcode pattern replaces its country's.
	 *
	 * @return array<string, string>
	 */
	private static function postalCodeOverridesIn(string $countryCode): array
	{
		$overrides = [];

		foreach (self::subdivisions()->getAll([$countryCode]) as $code => $subdivision) {
			$pattern = $subdivision->getPostalCodePattern();

			if ($pattern !== null) {
				$overrides["{$countryCode}-{$code}"] = $pattern;
			}
		}

		return $overrides;
	}

	/**
	 * Our part names for a list of the addressing library's field names, de-duplicated and in
	 * the order an address is written.
	 *
	 * Three upstream address lines collapse to one `street`, and everything this library does
	 * not model — the name fields, `sortingCode`, `organization` — falls away.
	 *
	 * The addressing library's docblocks say these are `AddressField` objects; at runtime they
	 * are the strings those objects' constants hold. Typed for what arrives rather than for
	 * what is promised, so the comparison is honest either way.
	 *
	 * @param array<int, mixed> $fields
	 * @return list<string>
	 */
	private static function translate(array $fields): array
	{
		$names = self::nameEach($fields);
		$parts = [];

		foreach (self::PARTS as $field => $part) {
			if (in_array($field, $names, true) && !in_array($part, $parts, true)) {
				$parts[] = $part;
			}
		}

		return $parts;
	}

	/**
	 * The addressing library's field names, read as strings whatever shape they arrive in.
	 *
	 * Its docblocks say these are `AddressField` objects; at runtime they are the strings those
	 * objects' constants hold. Comparing strictly against one shape and being handed the other
	 * fails silently in the direction that matters — `requiredParts` empties, every requiredness
	 * check skips, and an address is never incomplete again. `composer.json` allows `^2.0`, so an
	 * update inside the permitted range could do it and nothing would go red.
	 *
	 * So the shape is read rather than assumed, and anything unreadable raises instead of quietly
	 * contributing nothing.
	 *
	 * @param array<int, mixed> $fields
	 * @return list<string>
	 * @throws UnreadableAddressFormat if a field is none of the shapes this can read
	 */
	private static function nameEach(array $fields): array
	{
		return array_values(array_map(
			static function (mixed $field): string {
				if (is_string($field)) {
					return $field;
				}

				// A backed enum is the shape the upstream docblocks describe, and the likeliest
				// thing a future major would switch to.
				if ($field instanceof BackedEnum) {
					return (string) $field->value;
				}

				if ($field instanceof Stringable) {
					return (string) $field;
				}

				throw UnreadableAddressFormat::fieldIsNotReadable($field);
			},
			$fields,
		));
	}

	/**
	 * How many address lines a country's format offers.
	 *
	 * Derived rather than hardcoded so it follows the data, though the data is unanimous: all
	 * 206 countries use exactly three. A fourth line is therefore invalid data rather than a
	 * formatting problem for a port to solve.
	 *
	 * @param array<int, mixed> $usedFields
	 */
	private static function lineLimitOf(array $usedFields): int
	{
		$names = self::nameEach($usedFields);
		$lines = 0;

		foreach (['addressLine1', 'addressLine2', 'addressLine3'] as $line) {
			if (in_array($line, $names, true)) {
				++$lines;
			}
		}

		return $lines;
	}

	/**
	 * How many lines a street may run to when no country has been named yet.
	 *
	 * The generic format the addressing library falls back to, which every real country happens
	 * to agree with — all 206 use exactly three. That unanimity is why this one bound stays
	 * declarable on a field that allows any country, when none of the others can be.
	 */
	public static function genericStreetLineLimit(): int
	{
		// An unknown code gets the generic definition, which is the fallback itself.
		return self::lineLimitOf(self::formats()->get('ZZ')->getUsedFields());
	}

	/** Memoised: building these is not free and neither changes within a request. */
	private static function formats(): AddressFormatRepository
	{
		static $repository = null;

		return $repository ??= new AddressFormatRepository();
	}

	/** @see self::formats() */
	private static function subdivisions(): SubdivisionRepository
	{
		static $repository = null;

		return $repository ??= new SubdivisionRepository();
	}
}
