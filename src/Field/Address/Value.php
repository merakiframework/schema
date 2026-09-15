<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Address;

use Meraki\Schema\Field\ParsedValue;

/**
 * One postal or street address, held whole.
 *
 * Every part is optional, and deliberately so: this object holds whatever was submitted, including
 * a half-filled form on its way to being reported as invalid. How much of it is *required* is the
 * field's business — see `Address::allowWithoutStreet()` — not this object's.
 *
 * The part names follow Google's libaddressinput, because that is where the per-country rules
 * come from, with two exceptions: the street lines are `line1`/`line2` rather than upstream's
 * `addressLine1`/`addressLine2`, so they read as `line1` rather than stuttering as
 * `address_line1`; and `countryCode` has no upstream counterpart, because it is what *selects*
 * the format rather than being part of it.
 *
 * Properties are camelCase and array keys are snake_case, which is the convention a form sends
 * and a database column uses. {@see self::fromInput()} and {@see self::toArray()} are the seam.
 *
 * This is the field's *internal* representation — what arrived, cleaned up so that everything
 * downstream reads one shape: blanks become nulls, and a country given by name becomes its code.
 * There is deliberately no `__toString()`, because the order and punctuation an address takes is
 * per-country (libaddressinput's business) and rendering is the UI's. {@see self::toArray()} is
 * how you get at the parts.
 */
final readonly class Value implements ParsedValue
{
	/**
	 * Part names as they appear in submitted data, mapped to the property holding them.
	 *
	 * @var array<string, string>
	 */
	public const PARTS = [
		'organization' => 'organization',
		'line1' => 'line1',
		'line2' => 'line2',
		'dependent_locality' => 'dependentLocality',
		'locality' => 'locality',
		'administrative_area' => 'administrativeArea',
		'postal_code' => 'postalCode',
		// `country` rather than `country_code`, because either a code or a name may be submitted —
		// `Address::process()` canonicalises it to the code this property holds.
		'country' => 'countryCode',
	];

	/**
	 * @param string|null $administrativeArea a state, province or region
	 * @param string|null $locality a city, town or suburb
	 * @param string|null $dependentLocality a neighbourhood or dependent locality, where a
	 *        country uses one
	 * @param string|null $countryCode ISO 3166-1 alpha-2 once `Address::process()` has canonicalised
	 *        it, but as submitted — possibly a full country name — before that
	 */
	public function __construct(
		public ?string $organization = null,
		public ?string $line1 = null,
		public ?string $line2 = null,
		public ?string $dependentLocality = null,
		public ?string $locality = null,
		public ?string $administrativeArea = null,
		public ?string $postalCode = null,
		public ?string $countryCode = null,
	) {
	}

	/**
	 * Reads the snake_cased array a form submits. Unknown keys are ignored, and a part that is
	 * absent or not a string becomes `null`.
	 *
	 * **An empty string is not absence.** `''` is kept as `''`, because submitting it was a
	 * decision: a JSON client sending `"line1": ""` said something, and treating that as "no line
	 * one" would be guessing at the opposite. A form that submits `''` for a box nobody touched is
	 * a rendering artefact, and stripping it is the port's job — see docs/CODING-STYLE.md. Nothing
	 * is trimmed here for the same reason.
	 *
	 * Total: there is no array it refuses, because it runs on untrusted input.
	 *
	 * @param array<string, mixed> $parts
	 */
	/**
	 * Every part, compared exactly.
	 *
	 * Exact is right here because the parts arrive canonicalised: a country submitted as `AU` and
	 * one submitted as `Australia` are already the same `AU` by the time they reach this object, so
	 * two addresses that differ only in how they were typed are one address without this having to
	 * know anything about postal conventions.
	 *
	 * What it does *not* do is decide that two differently-written street lines are the same place.
	 * That is an address-normalisation problem, it is locale-specific and genuinely hard, and
	 * guessing at it would silently merge two distinct addresses.
	 */
	public function equals(ParsedValue $other): bool
	{
		return $other instanceof self
			&& $this->organization === $other->organization
			&& $this->line1 === $other->line1
			&& $this->line2 === $other->line2
			&& $this->dependentLocality === $other->dependentLocality
			&& $this->locality === $other->locality
			&& $this->administrativeArea === $other->administrativeArea
			&& $this->postalCode === $other->postalCode
			&& $this->countryCode === $other->countryCode;
	}

	public static function fromInput(array $parts): self
	{
		$read = static function (string $key) use ($parts): ?string {
			$value = $parts[$key] ?? null;

			return is_string($value) ? $value : null;
		};

		return new self(
			organization: $read('organization'),
			line1: $read('line1'),
			line2: $read('line2'),
			dependentLocality: $read('dependent_locality'),
			locality: $read('locality'),
			administrativeArea: $read('administrative_area'),
			postalCode: $read('postal_code'),
			// Left exactly as submitted. A code and a name are both acceptable, and only the field
			// knows which countries exist, so canonicalising is its job rather than this one's.
			countryCode: $read('country'),
		);
	}

	/**
	 * The snake_cased form, every part present even when null, so a consumer can rely on the
	 * shape rather than testing for keys.
	 *
	 * @return array<string, string|null>
	 */
	public function toArray(): array
	{
		$parts = [];

		foreach (self::PARTS as $key => $property) {
			$parts[$key] = $this->{$property};
		}

		return $parts;
	}

	/**
	 * Whether any part was supplied at all. An address with nothing in it is not a vague
	 * address; it is an absent one.
	 */
	public function isEmpty(): bool
	{
		foreach (self::PARTS as $property) {
			if ($this->{$property} !== null) {
				return false;
			}
		}

		return true;
	}

	/**
	 * A copy with the country replaced by its canonical code.
	 */
	public function withCountryCode(string $countryCode): self
	{
		return new self(
			organization: $this->organization,
			line1: $this->line1,
			line2: $this->line2,
			dependentLocality: $this->dependentLocality,
			locality: $this->locality,
			administrativeArea: $this->administrativeArea,
			postalCode: $this->postalCode,
			countryCode: $countryCode,
		);
	}

	/**
	 * A part by the name submitted data uses — `administrative_area`, not `administrativeArea`.
	 *
	 * Which is what the constraints address parts by, so a failure can say *which* part it was
	 * about in the same vocabulary the author wrote.
	 */
	public function partNamed(string $key): ?string
	{
		$property = self::PARTS[$key] ?? null;

		return $property === null ? null : $this->{$property};
	}

}
