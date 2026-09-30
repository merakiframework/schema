<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Address;

use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;

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
	 *        one, and the value refuses one without it, so it is a fact about the shape rather
	 *        than something a country asks for.
	 * @param list<string> $usedParts what an address here may have at all, floor or no floor
	 * @param list<string> $subdivisions ISO 3166-2 codes, written in full — `AU-QLD`, not `QLD`.
	 *        Empty where a country has none on file, which includes eight that use one without
	 *        publishing a list.
	 * @param string|null $postalCodeFormat null where a country has no postcode
	 * @param int $streetLineLimit how many lines a street may run to
	 */
	public function __construct(
		public string $country,
		public array $requiredParts,
		public array $usedParts,
		public array $subdivisions,
		public ?string $postalCodeFormat,
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
			self::subdivisionsOf($countryCode),
			$format->getPostalCodePattern(),
			self::lineLimitOf($format->getUsedFields()),
		);
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
		$parts = [];

		foreach (self::PARTS as $field => $part) {
			if (in_array($field, $fields, true) && !in_array($part, $parts, true)) {
				$parts[] = $part;
			}
		}

		return $parts;
	}

	/**
	 * The country's subdivisions as ISO 3166-2 writes them.
	 *
	 * The addressing data stores the suffix alone — `QLD` — because the country is implied by
	 * the lookup. Written out in full here so the value is unambiguous on its own, which matters
	 * when a rule compares this part across fields or a consumer reads it without the country
	 * beside it.
	 *
	 * @return list<string>
	 */
	private static function subdivisionsOf(string $countryCode): array
	{
		$codes = array_keys(self::subdivisions()->getList([$countryCode]));

		// `string|int` because a numeric subdivision code is an int by the time it is an
		// array key. Declaring it keeps this honest rather than leaning on weak-mode
		// coercion in an internal function's callback.
		return array_map(static fn(string|int $code): string => "{$countryCode}-{$code}", $codes);
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
		$lines = 0;

		foreach (['addressLine1', 'addressLine2', 'addressLine3'] as $line) {
			if (in_array($line, $usedFields, true)) {
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
