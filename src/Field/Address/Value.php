<?php
declare(strict_types=1);

namespace Meraki\Schema\Field\Address;

use CommerceGuys\Addressing\Country\CountryRepository;
use Meraki\Schema\Field\MalformedValue;
use Meraki\Schema\Comparison\Equality;
use Meraki\Schema\Field\HasParts;
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
 * and a database column uses. The constructor and {@see self::toArray()} are the seam.
 *
 * This is the field's *internal* representation — what arrived, cleaned up so that everything
 * downstream reads one shape: blanks become nulls, and a country given by name becomes its code.
 * There is deliberately no `__toString()`, because the order and punctuation an address takes is
 * per-country (libaddressinput's business) and rendering is the UI's. {@see self::toArray()} is
 * how you get at the parts.
 */
final readonly class Value implements ParsedValue, HasParts
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
	public ?string $organization;
	public ?string $line1;
	public ?string $line2;
	public ?string $dependentLocality;
	public ?string $locality;
	public ?string $administrativeArea;
	public ?string $postalCode;

	/** The ISO 3166-1 alpha-2 code, whether a code or a country's name was submitted. */
	public ?string $countryCode;

	/**
	 * Takes the record a field takes, so there is one answer to "what is an address here".
	 *
	 * Total about the *parts*: an absent or non-string part becomes null, and `''` is kept as
	 * `''` because submitting it was a decision. Nothing is trimmed — that is the port's job.
	 *
	 * Two things it refuses, and both are about whether this is an address at all rather than
	 * whether it is an acceptable one:
	 *
	 * - **Nothing in it.** An address with no parts is not a vague address; it is not an
	 *   address.
	 * - **No country.** A postcode means nothing without one — `4700` is Rockhampton in
	 *   Australia and something else elsewhere — so an address without a country never
	 *   described a place. The same pairing money makes with a currency.
	 *
	 * A country that is *present but unrecognised* is kept and passes: `allowedCountries` is
	 * the constraint that reports it, and it can name the list it should have been from, which
	 * is more use than refusing here.
	 *
	 * @param object $address with any of the keys in {@see self::PARTS}
	 * @throws MalformedValue if it is empty, or names no country
	 */
	public function __construct(object $address)
	{
		$parts = get_object_vars($address);

		$read = static function (string $key) use ($parts): ?string {
			$value = $parts[$key] ?? null;

			return is_string($value) ? $value : null;
		};

		$this->organization = $read('organization');
		$this->line1 = $read('line1');
		$this->line2 = $read('line2');
		$this->dependentLocality = $read('dependent_locality');
		$this->locality = $read('locality');
		$this->administrativeArea = $read('administrative_area');
		$this->postalCode = $read('postal_code');

		// The one thing canonicalised: a code and a country's name are two spellings of one
		// country, and ISO 3166-1 says which of them is the code. An unrecognised string is
		// left exactly as it came, for `allowedCountries` to report.
		$country = $read('country');
		$this->countryCode = $country === null ? null : (self::codeFor($country) ?? $country);

		if ($this->isEmpty()) {
			throw MalformedValue::of(self::class, 'it has no parts at all');
		}

		if ($this->countryCode === null || $this->countryCode === '') {
			throw MalformedValue::of(self::class, 'it names no country, and an address without one describes no place');
		}
	}

	/**
	 * The readable way to write one by hand — a rule's bound, a test.
	 *
	 * A convenience over the constructor rather than a second way in: it builds the record a
	 * form would submit and hands it over, so the invariant is enforced in one place.
	 *
	 * @throws MalformedValue if it is empty, or names no country
	 */
	public static function of(
		?string $line1 = null,
		?string $locality = null,
		?string $administrativeArea = null,
		?string $postalCode = null,
		?string $country = null,
		?string $organization = null,
		?string $line2 = null,
		?string $dependentLocality = null,
	): self {
		return new self((object) [
			'organization' => $organization,
			'line1' => $line1,
			'line2' => $line2,
			'dependent_locality' => $dependentLocality,
			'locality' => $locality,
			'administrative_area' => $administrativeArea,
			'postal_code' => $postalCode,
			'country' => $country,
		]);
	}

	/**
	 * Exact on every part, because both sides are already canonical: the country is a code by
	 * the time it gets here, whichever spelling was submitted.
	 *
	 * What it does *not* do is decide that two differently-written street lines are the same
	 * place. That is an address-normalisation problem, it is locale-specific and genuinely hard,
	 * and guessing at it would silently merge two distinct addresses.
	 */
	public function equals(Equality $other): bool
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

	/**
	 * The ISO 3166-1 code for a country written as a code or as a name, or null for neither.
	 *
	 * Public because the *field* needs the same answer when it checks an author's allow-list,
	 * and two implementations of "is this a country" would eventually disagree.
	 */
	public static function codeFor(string $country): ?string
	{
		static $repository = null;
		$known = ($repository ??= new CountryRepository())->getList();

		if (isset($known[strtoupper($country)])) {
			return strtoupper($country);
		}

		foreach ($known as $code => $name) {
			if (mb_strtolower($name) === mb_strtolower($country)) {
				return $code;
			}
		}

		return null;
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
		return self::of(
			line1: $this->line1,
			locality: $this->locality,
			administrativeArea: $this->administrativeArea,
			postalCode: $this->postalCode,
			country: $countryCode,
			organization: $this->organization,
			line2: $this->line2,
			dependentLocality: $this->dependentLocality,
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

	/**
	 * The eight lines libaddressinput models, named as they arrive. `country` rather than
	 * `countryCode`, because that is the key submitted input uses and the name the
	 * `allowedCountries` constraint reports its part under.
	 *
	 * @return list<string>
	 */
	public static function partNames(): array
	{
		return ['organization', 'line1', 'line2', 'dependent_locality', 'locality', 'administrative_area', 'postal_code', 'country'];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function parts(): array
	{
		return [
			'organization' => $this->organization,
			'line1' => $this->line1,
			'line2' => $this->line2,
			'dependent_locality' => $this->dependentLocality,
			'locality' => $this->locality,
			'administrative_area' => $this->administrativeArea,
			'postal_code' => $this->postalCode,
			'country' => $this->countryCode,
		];
	}
}
